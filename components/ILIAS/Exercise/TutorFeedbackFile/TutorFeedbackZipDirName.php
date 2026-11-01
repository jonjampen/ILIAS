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

/**
 * Builds and parses the directory names used in multi-feedback zip files.
 *
 * Individual assignments: <lastname>_<firstname>_<login>_<user_id>
 * Team assignments:       Team_<lastname1>_<lastname2>_..._t<team_id>
 *
 * Only the last "_"-separated segment is relevant for the assignment of
 * files, the rest is for human readability only.
 */
class TutorFeedbackZipDirName
{
    public const TEAM_PREFIX = "t";

    public function forUser(
        string $lastname,
        string $firstname,
        string $login,
        int $user_id
    ): string {
        return $lastname . "_" . $firstname . "_" . $login . "_" . $user_id;
    }

    /**
     * @param string[] $member_lastnames
     */
    public function forTeam(int $team_id, array $member_lastnames): string
    {
        $parts = array_merge(["Team"], $member_lastnames, [self::TEAM_PREFIX . $team_id]);
        return implode("_", $parts);
    }

    protected function getIdSegment(string $dir): string
    {
        $dir_parts = explode("_", trim($dir));
        return (string) end($dir_parts);
    }

    /**
     * Returns the user id of a directory of an individual assignment, 0 if not found.
     */
    public function parseUserId(string $dir): int
    {
        $seg = $this->getIdSegment($dir);
        return ctype_digit($seg) ? (int) $seg : 0;
    }

    /**
     * Returns the team id of a directory of a team assignment, 0 if not found.
     */
    public function parseTeamId(string $dir): int
    {
        $seg = $this->getIdSegment($dir);
        if (str_starts_with($seg, self::TEAM_PREFIX)) {
            $id = substr($seg, strlen(self::TEAM_PREFIX));
            return ctype_digit($id) ? (int) $id : 0;
        }
        return 0;
    }
}
