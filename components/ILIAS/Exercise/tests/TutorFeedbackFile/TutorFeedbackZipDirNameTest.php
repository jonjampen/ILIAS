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

use PHPUnit\Framework\TestCase;
use ILIAS\Exercise\TutorFeedbackFile\TutorFeedbackZipDirName;

class TutorFeedbackZipDirNameTest extends TestCase
{
    protected TutorFeedbackZipDirName $dir_name;

    protected function setUp(): void
    {
        $this->dir_name = new TutorFeedbackZipDirName();
    }

    public function testUserDirRoundTrip(): void
    {
        $dir = $this->dir_name->forUser("Doe", "John", "jdoe", 42);
        $this->assertEquals("Doe_John_jdoe_42", $dir);
        $this->assertEquals(42, $this->dir_name->parseUserId($dir));
        $this->assertEquals(0, $this->dir_name->parseTeamId($dir));
    }

    public function testUserDirWithUnderscoresInName(): void
    {
        $dir = $this->dir_name->forUser("Van_der_Berg", "Anna", "a_vdb", 7);
        $this->assertEquals(7, $this->dir_name->parseUserId($dir));
    }

    public function testTeamDirRoundTrip(): void
    {
        $dir = $this->dir_name->forTeam(15, ["Doe", "Smith"]);
        $this->assertEquals("Team_Doe_Smith_t15", $dir);
        $this->assertEquals(15, $this->dir_name->parseTeamId($dir));
        $this->assertEquals(0, $this->dir_name->parseUserId($dir));
    }

    public function testTeamDirWithoutMembers(): void
    {
        $dir = $this->dir_name->forTeam(3, []);
        $this->assertEquals("Team_t3", $dir);
        $this->assertEquals(3, $this->dir_name->parseTeamId($dir));
    }

    public function testInvalidDirs(): void
    {
        $this->assertEquals(0, $this->dir_name->parseUserId("foo"));
        $this->assertEquals(0, $this->dir_name->parseUserId("Doe_John_12x"));
        $this->assertEquals(0, $this->dir_name->parseTeamId("Team_Doe_t"));
        $this->assertEquals(0, $this->dir_name->parseTeamId("Team_Doe_tx1"));
        $this->assertEquals(0, $this->dir_name->parseTeamId(""));
    }
}
