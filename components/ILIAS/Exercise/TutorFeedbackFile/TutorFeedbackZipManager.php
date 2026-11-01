<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Exercise\TutorFeedbackFile;

use ILIAS\Exercise\InternalRepoService;
use ILIAS\Exercise\InternalDomainService;
use ILIAS\FileUpload\DTO\UploadResult;

class TutorFeedbackZipManager
{
    protected \ilExcTutorFeedbackFileStakeholder $user_feedback_stakeholder;
    protected \ilExcTutorTeamFeedbackFileStakeholder $team_feedback_stakeholder;
    protected TutorFeedbackFileRepository $participant_repo;
    protected TutorFeedbackFileTeamRepository $team_repo;
    protected \ILIAS\Exercise\Team\TeamDBRepository $team_db_repo;
    protected \ilExcTutorFeedbackZipStakeholder $stakeholder;
    protected int $ass_id;
    protected InternalDomainService $domain;
    protected TutorFeedbackZipRepository $repo;

    public function __construct(
        InternalRepoService $repo,
        InternalDomainService $domain,
        \ilExcTutorFeedbackZipStakeholder $stakeholder,
        \ilExcTutorFeedbackFileStakeholder $user_feedback_stakeholder
    ) {
        $this->domain = $domain;
        $this->repo = $repo->tutorFeedbackZip();
        $this->stakeholder = $stakeholder;
        $this->user_feedback_stakeholder = $user_feedback_stakeholder;
        $this->participant_repo = $repo->tutorFeedbackFile();
        $this->team_repo = $repo->tutorFeedbackFileTeam();
        $this->team_db_repo = $repo->team();
        $this->team_feedback_stakeholder = new \ilExcTutorTeamFeedbackFileStakeholder();
    }

    public function usesTeams(int $ass_id): bool
    {
        $types = \ilExAssignmentTypes::getInstance();
        return $types->getById(\ilExAssignment::lookupType($ass_id))->usesTeams();
    }

    /**
     * Get all teams of an assignment that have at least one of the given members
     *
     * @param int[] $member_ids
     * @return array<int, int[]> team id => member user ids
     */
    protected function getTeamsOfMembers(int $ass_id, array $member_ids): array
    {
        $teams = [];
        foreach ($this->team_db_repo->getUserTeamMap($ass_id) as $user_id => $team_id) {
            $teams[$team_id][] = $user_id;
        }
        return array_filter(
            $teams,
            static fn(array $team_members): bool => count(array_intersect($team_members, $member_ids)) > 0
        );
    }

    protected function toAscii(string $filename): string
    {
        global $DIC;
        return (new \ilFileServicesPolicy($DIC->fileServiceSettings()))->ascii($filename);
    }

    public function getMultiFeedbackStructureFile(
        \ilExAssignment $assignment,
        \ilObjExercise $exercise
    ): object {
        $access = $this->domain->access();
        $exmem = new \ilExerciseMembers($exercise);

        $tmp_file = tmpfile();
        $tmp_location = stream_get_meta_data($tmp_file)['uri'];
        $zip = new \ZipArchive();
        $res = $zip->open($tmp_location, \ZipArchive::OVERWRITE);

        // send and delete the zip file
        $base_name = trim(str_replace(" ", "_", $assignment->getTitle() . "_" . $assignment->getId()));
        $base_name = "multi_feedback_" . $this->toAscii($base_name);

        // create subfolders <lastname>_<firstname>_<id> for each participant
        $mems = $exmem->getMembers();

        $mems = $access->filterUserIdsByRbacOrPositionOfCurrentUser(
            'edit_submissions_grades',
            'edit_submissions_grades',
            $exercise->getRefId(),
            $mems
        );
        $dir_name = new TutorFeedbackZipDirName();
        if ($this->usesTeams($assignment->getId())) {
            // create subfolders Team_<lastname1>_<lastname2>_..._t<team_id> for each team
            foreach ($this->getTeamsOfMembers($assignment->getId(), $mems) as $team_id => $team_members) {
                sort($team_members);
                $lastnames = array_map(
                    static fn(int $user_id): string => (string) \ilObjUser::_lookupName($user_id)["lastname"],
                    $team_members
                );
                $subdir = $this->toAscii($dir_name->forTeam($team_id, $lastnames));
                $zip->addEmptyDir($base_name . "/" . $subdir);
            }
        } else {
            foreach ($mems as $mem) {
                $name = \ilObjUser::_lookupName($mem);
                $subdir = $dir_name->forUser(
                    (string) $name["lastname"],
                    (string) $name["firstname"],
                    (string) $name["login"],
                    (int) $name["user_id"]
                );
                $subdir = $this->toAscii($subdir);
                $zip->addEmptyDir($base_name . "/" . $subdir);
            }
        }
        $zip->close();
        $ret = new \StdClass();
        $ret->content = file_get_contents($tmp_location);
        $ret->filename = $base_name . ".zip";
        return $ret;
    }

    public function importFromUploadResult(
        int $ass_id,
        int $tutor_id,
        UploadResult $result
    ): string {
        $this->repo->deleteCurrent($ass_id, $tutor_id, $this->stakeholder);
        return $this->repo->importFromUploadResult(
            $ass_id,
            $tutor_id,
            $result,
            $this->stakeholder
        );
    }

    public function getFiles(\ilObjExercise $exc, int $ass_id, int $tutor_id): array
    {
        $exmem = new \ilExerciseMembers($exc);
        $mems = $exmem->getMembers();

        if ($this->usesTeams($ass_id)) {
            return $this->repo->getTeamFiles(
                $ass_id,
                $tutor_id,
                $this->getTeamsOfMembers($ass_id, $mems)
            );
        }
        return $this->repo->getFiles($ass_id, $tutor_id, $mems);
    }

    public function getFileMd5(int $user_id, string $filename): string
    {
        return md5($user_id . ":" . $filename);
    }

    public function saveMultiFeedbackFiles(
        \ilObjExercise $exc,
        int $ass_id,
        int $tutor_id,
        array $file_md5s
    ): void {
        $notification = $this->domain->notification($exc->getRefId());
        $uses_teams = $this->usesTeams($ass_id);
        // in team assignments user_id is a representative member of the team,
        // the team repository resolves the team collection via this user
        $target_repo = $uses_teams
            ? $this->team_repo
            : $this->participant_repo;
        $target_stakeholder = $uses_teams
            ? $this->team_feedback_stakeholder
            : $this->user_feedback_stakeholder;
        foreach ($this->getFiles($exc, $ass_id, $tutor_id) as $file) {
            $user_id = (int) $file["user_id"];
            $md5 = $this->getFileMd5((int) $file["user_id"], $file["file"]);
            if (is_array($file_md5s[$user_id] ?? null) && in_array($md5, $file_md5s[$user_id])) {
                if (!$target_repo->hasCollection($ass_id, $user_id)) {
                    $target_repo->createCollection($ass_id, $user_id);
                }
                $target_collection = $target_repo->getCollection($ass_id, $user_id);
                if ($target_collection) {
                    $this->repo->addFileFromZipToCollection(
                        $ass_id,
                        $tutor_id,
                        $file["full_entry"],
                        $target_collection,
                        $target_stakeholder
                    );
                }

                $ass = new \ilExAssignment($ass_id);
                $submission = new \ilExSubmission($ass, $user_id);
                $feedback_id = $submission->getFeedbackId();
                $noti_rec_ids = $submission->getUserIds();

                if ($feedback_id) {
                    if ($noti_rec_ids) {
                        foreach ($noti_rec_ids as $noti_user_id) {
                            $member_status = $ass->getMemberStatus($noti_user_id);
                            $member_status->setFeedback(true);
                            $member_status->update();
                        }

                        $notification->sendFeedbackNotification(
                            $ass->getId(),
                            $noti_rec_ids,
                            $file["file"]
                        );
                    }
                }
            }
        }
    }

}
