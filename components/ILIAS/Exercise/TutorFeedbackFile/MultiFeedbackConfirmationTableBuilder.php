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

use ILIAS\Exercise\InternalDomainService;
use ILIAS\Exercise\InternalGUIService;
use ILIAS\Repository\RetrievalInterface;
use ILIAS\Repository\Table\CommonTableBuilder;
use ILIAS\Repository\Table\TableAdapterGUI;

class MultiFeedbackConfirmationTableBuilder extends CommonTableBuilder
{
    public function __construct(
        protected InternalDomainService $domain,
        protected InternalGUIService $gui,
        protected \ilExAssignment $assignment,
        object $parent_gui,
        string $parent_cmd
    ) {
        parent::__construct($parent_gui, $parent_cmd, false);
    }

    protected function getId(): string
    {
        return 'multi_feedback_confirmation';
    }

    protected function getTitle(): string
    {
        return $this->domain->lng()->txt('exc_multi_feedback_files');
    }

    protected function getRetrieval(): RetrievalInterface
    {
        return $this->domain->multiFeedbackConfirmationRetrieval($this->assignment);
    }

    protected function usesTeams(): bool
    {
        return $this->assignment->getAssignmentType()->usesTeams();
    }

    protected function transformRow(array $data_row): array
    {
        if ($this->usesTeams()) {
            return [
                'id' => $data_row['id'],
                'members' => $data_row['members'],
                'file' => $data_row['file']
            ];
        }
        return [
            'id' => $data_row['id'],
            'lastname' => $data_row['lastname'],
            'firstname' => $data_row['firstname'],
            'login' => $data_row['login'],
            'file' => $data_row['file']
        ];
    }

    protected function build(TableAdapterGUI $table): TableAdapterGUI
    {
        $lng = $this->domain->lng();

        if ($this->usesTeams()) {
            return $table
                ->listingColumn('members', $lng->txt('exc_team'))
                ->textColumn('file', $lng->txt('file'))
                ->multiAction('saveMultiFeedback', $lng->txt('save'));
        }

        return $table
            ->textColumn('lastname', $lng->txt('lastname'))
            ->textColumn('firstname', $lng->txt('firstname'))
            ->textColumn('login', $lng->txt('login'))
            ->textColumn('file', $lng->txt('file'))
            ->multiAction('saveMultiFeedback', $lng->txt('save'));
    }
}
