<?php

namespace App\Data;

readonly class UserTeam
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $isPersonal,
        public ?string $role,
        public ?string $roleLabel,
        public ?bool $isCurrent = null,
        /**
         * How many people are in it.
         *
         * The screen used to badge a team "Personal", which reads as a
         * category when it is really just a fact about where the team came
         * from — and a misleading one once somebody has been invited in. The
         * honest thing to show is how many people are actually there.
         */
        public ?int $memberCount = null,
    ) {
        //
    }
}
