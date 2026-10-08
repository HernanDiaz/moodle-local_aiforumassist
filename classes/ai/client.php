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
 * Sends a prompt to whatever provider the site configured in Moodle's AI subsystem (generate_text).
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /**
     * Generate text. Never throws: failures come back as an unsuccessful result.
     *
     * @param int $contextid Context the call belongs to (the course).
     * @param int $userid User the call is made for (Tiko, for the assistant's background work).
     * @param string $prompt The whole prompt (Moodle 4.5's generate_text has no separate system instruction).
     * @return result
     */
    public static function generate(int $contextid, int $userid, string $prompt): result {
        $start = microtime(true);
        try {
            $action = new \core_ai\aiactions\generate_text(contextid: $contextid, userid: $userid, prompttext: $prompt);
            $response = \core\di::get(\core_ai\manager::class)->process_action($action);
        } catch (\Throwable $e) {
            // Moodle 4.5's OpenAI provider throws instead of returning an error when the request gets no HTTP
            // answer at all (network or TLS failure, e.g. a proxy or antivirus intercepting HTTPS).
            return new result(false, '', $e->getMessage(), durationms: self::elapsed($start));
        }
        [$provider, $model] = self::origin($userid);
        if (!$response->get_success()) {
            $error = trim($response->get_errorcode() . ': ' . $response->get_errormessage(), ' :');
            $error = $error !== '' ? $error : 'AI call failed';
            return new result(false, '', $error, null, null, $provider, $model, self::elapsed($start));
        }
        $data = $response->get_response_data();
        return new result(
            true,
            (string) ($data['generatedcontent'] ?? ''),
            null,
            isset($data['prompttokens']) ? (int) $data['prompttokens'] : null,
            isset($data['completiontokens']) ? (int) $data['completiontokens'] : null,
            $provider,
            $model,
            self::elapsed($start)
        );
    }

    /**
     * Milliseconds since a microtime(true) value.
     *
     * @param float $start Start time.
     * @return int
     */
    private static function elapsed(float $start): int {
        return (int) round((microtime(true) - $start) * 1000);
    }

    /**
     * Provider of the user's latest generate_text call, and its configured model when known.
     *
     * Moodle 4.5 records the provider of each call but not the model, so the model is the provider's
     * configured default; it is left empty rather than guessed.
     *
     * @param int $userid User the call was made for.
     * @return array{0: string|null, 1: string|null}
     */
    private static function origin(int $userid): array {
        global $DB;
        $records = $DB->get_records(
            'ai_action_register',
            ['actionname' => 'generate_text', 'userid' => $userid],
            'id DESC',
            '*',
            0,
            1
        );
        $record = reset($records);
        if (!$record) {
            return [null, null];
        }
        $model = !empty($record->model) ? (string) $record->model : get_config($record->provider, 'action_generate_text_model');
        return [
            \core_text::substr((string) $record->provider, 0, 64),
            is_string($model) && $model !== '' ? \core_text::substr($model, 0, 128) : null,
        ];
    }
}
