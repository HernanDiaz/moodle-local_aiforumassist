<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_aiforumassist\ai;

/**
 * Outcome of one call to the AI subsystem.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result {
    /** @var bool Whether the provider answered. */
    public bool $success;

    /** @var string Generated text ('' on failure). */
    public string $text;

    /** @var string|null Error message on failure. */
    public ?string $error;

    /** @var int|null Prompt tokens, when the provider reports them. */
    public ?int $tokensin;

    /** @var int|null Completion tokens, when the provider reports them. */
    public ?int $tokensout;

    /** @var string|null Provider that handled the call. */
    public ?string $provider;

    /** @var string|null Model, when it can be known. */
    public ?string $model;

    /** @var int Duration of the call in milliseconds. */
    public int $durationms;

    /**
     * Constructor.
     *
     * @param bool $success Whether the provider answered.
     * @param string $text Generated text ('' on failure).
     * @param string|null $error Error message on failure.
     * @param int|null $tokensin Prompt tokens.
     * @param int|null $tokensout Completion tokens.
     * @param string|null $provider Provider that handled the call.
     * @param string|null $model Model, when it can be known.
     * @param int $durationms Duration of the call.
     */
    public function __construct(
        bool $success,
        string $text,
        ?string $error,
        ?int $tokensin = null,
        ?int $tokensout = null,
        ?string $provider = null,
        ?string $model = null,
        int $durationms = 0
    ) {
        $this->success = $success;
        $this->text = $text;
        $this->error = $error;
        $this->tokensin = $tokensin;
        $this->tokensout = $tokensout;
        $this->provider = $provider;
        $this->model = $model;
        $this->durationms = $durationms;
    }
}
