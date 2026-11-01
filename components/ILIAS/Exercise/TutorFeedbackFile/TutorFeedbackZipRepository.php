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

use ILIAS\Repository\IRSS\IRSSWrapper;
use ILIAS\ResourceStorage\Collection\ResourceCollection;
use ILIAS\ResourceStorage\Stakeholder\ResourceStakeholder;
use ILIAS\FileUpload\DTO\UploadResult;

class TutorFeedbackZipRepository
{
    protected IRSSWrapper $wrapper;
    protected \ilDBInterface $db;

    public function __construct(
        IRSSWrapper $wrapper,
        \ilDBInterface $db
    ) {
        $this->db = $db;
        $this->wrapper = $wrapper;
    }

    public function getIdStringForAssAndTutorId(int $ass_id, int $tutor_id): string
    {
        $set = $this->db->queryF(
            "SELECT zip_rid FROM exc_multi_feedback " .
            " WHERE ass_id = %s AND tutor_id = %s ",
            ["integer", "integer"],
            [$ass_id, $tutor_id]
        );
        $rec = $this->db->fetchAssoc($set);
        return ($rec["zip_rid"] ?? "");
    }

    public function hasFile(int $ass_id, int $tutor_id): bool
    {
        $rid = $this->getIdStringForAssAndTutorId($ass_id, $tutor_id);
        return ($rid !== "");
    }

    public function deleteCurrent(
        int $ass_id,
        int $tutor_id,
        ResourceStakeholder $stakeholder
    ): void {
        $rid = $this->getIdStringForAssAndTutorId($ass_id, $tutor_id);
        $this->wrapper->deleteResource($rid, $stakeholder);
    }

    public function importFromUploadResult(
        int $ass_id,
        int $tutor_id,
        UploadResult $result,
        ResourceStakeholder $stakeholder
    ): string {
        $rid = $this->wrapper->importFileFromUploadResult(
            $result,
            $stakeholder
        );
        if ($rid !== "") {
            $this->db->replace(
                "exc_multi_feedback",
                [
                "tutor_id" => ["integer", $tutor_id],
                "ass_id" => ["integer", $ass_id],
            ],
                [
                    "zip_rid" => ["text", $rid]
                ]
            );
        }
        return $rid;
    }

    /**
     * Get all file entries of the uploaded zip that are located in a
     * participant (or team) directory: <base_dir>/<participant_dir>/<file>
     *
     * @return array<array{dir: string, file: string, full_entry: string}>
     */
    public function getEntries(int $ass_id, int $tutor_id): array
    {
        $entries = [];
        $rid = $this->getIdStringForAssAndTutorId($ass_id, $tutor_id);
        if ($rid !== "") {
            $zip = new \ZipArchive();
            if ($zip->open($this->wrapper->stream($rid)->getMetadata()['uri'], \ZipArchive::RDONLY)) {
                $cnt = $zip->count();
                for ($i = 0; $i < $cnt; $i++) {
                    $full_entry = $zip->getNameIndex($i);
                    $main_parts = explode("/", $full_entry);
                    if (count($main_parts) === 3 && trim($main_parts[2]) !== ""
                        && substr($main_parts[2], 0, 1) !== ".") {
                        $entries[] = [
                            "dir" => $main_parts[1],
                            "file" => $main_parts[2],
                            "full_entry" => $full_entry
                        ];
                    }
                }
                $zip->close();
            }
        }
        return $entries;
    }

    public function getFiles(int $ass_id, int $tutor_id, array $valid_members): array
    {
        $files = [];
        $dir_name = new TutorFeedbackZipDirName();
        foreach ($this->getEntries($ass_id, $tutor_id) as $entry) {
            $user_id = $dir_name->parseUserId($entry["dir"]);
            if (in_array($user_id, $valid_members)) {
                // read dir of user
                $name = \ilObjUser::_lookupName($user_id);
                $files[] = array(
                        "lastname" => $name["lastname"],
                        "firstname" => $name["firstname"],
                        "login" => $name["login"],
                        "user_id" => (int) $name["user_id"],
                        "full_entry" => $entry["full_entry"],
                        "file" => $entry["file"]);
            }
        }
        return $files;
    }

    /**
     * @param array<int, int[]> $valid_teams team id => member user ids
     */
    public function getTeamFiles(int $ass_id, int $tutor_id, array $valid_teams): array
    {
        $files = [];
        $dir_name = new TutorFeedbackZipDirName();
        foreach ($this->getEntries($ass_id, $tutor_id) as $entry) {
            $team_id = $dir_name->parseTeamId($entry["dir"]);
            if ($team_id > 0 && isset($valid_teams[$team_id]) && count($valid_teams[$team_id]) > 0) {
                $member_ids = $valid_teams[$team_id];
                sort($member_ids);
                $members = [];
                foreach ($member_ids as $member_id) {
                    $name = \ilObjUser::_lookupName($member_id);
                    $members[] = $name["lastname"] . ", " . $name["firstname"] . " [" . $name["login"] . "]";
                }
                $files[] = [
                    "team_id" => $team_id,
                    // a stable representative member, the team is resolved via this user
                    "user_id" => $member_ids[0],
                    "members" => $members,
                    "full_entry" => $entry["full_entry"],
                    "file" => $entry["file"]
                ];
            }
        }
        return $files;
    }

    public function addFileFromZipToCollection(
        int $ass_id,
        int $tutor_id,
        string $entry,
        ResourceCollection $target_collection,
        ResourceStakeholder $target_stakeholder
    ): void {
        $rid = $this->getIdStringForAssAndTutorId($ass_id, $tutor_id);
        if ($rid !== "") {
            $this->wrapper->addEntryOfZipResourceToCollection(
                $rid,
                $entry,
                $target_collection,
                $target_stakeholder
            );
        }
    }
}
