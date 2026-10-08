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

namespace local_aiforumassist;

/**
 * Tiko, the AI assistant: the user that signs automatic posts and makes the background AI calls.
 *
 * A user without login (auth "nologin"), named "Tiko (AI assistant)" in the site's language, with the
 * fox mascot as picture and a profile that says it is an AI. Administrators may rename it in the user's
 * profile, as long as the name keeps saying it is an AI (AI Act, article 50).
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tiko {
    /** Username of the Tiko user. */
    public const USERNAME = 'aiforumassist_tiko';

    /**
     * Id of the Tiko user, created when it does not exist.
     *
     * @return int
     */
    public static function user_id(): int {
        global $DB;
        $id = (int) get_config('local_aiforumassist', 'tikouserid');
        if ($id && $DB->record_exists('user', ['id' => $id, 'deleted' => 0])) {
            return $id;
        }
        return (int) self::create()->id;
    }

    /**
     * Create the Tiko user (or adopt an existing one with its username) and remember its id.
     *
     * @return \stdClass The user record.
     */
    public static function create(): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $user = $DB->get_record('user', ['username' => self::USERNAME, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
        if (!$user) {
            $lang = $CFG->lang ?: 'en';
            $strings = get_string_manager();
            $user = (object) [
                'auth' => 'nologin',
                'username' => self::USERNAME,
                'firstname' => 'Tiko',
                'lastname' => $strings->get_string('tiko_lastname', 'local_aiforumassist', null, $lang),
                'email' => \core_user::get_noreply_user()->email,
                'emailstop' => 1,
                'maildisplay' => 0,
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'lang' => $lang,
                'description' => $strings->get_string('tiko_description', 'local_aiforumassist', null, $lang),
                'descriptionformat' => FORMAT_HTML,
            ];
            $user->id = user_create_user($user, false, false);
            self::set_picture((int) $user->id);
        }
        set_config('tikouserid', $user->id, 'local_aiforumassist');
        return $user;
    }

    /**
     * Give Tiko the fox picture.
     *
     * @param int $userid The Tiko user.
     */
    private static function set_picture(int $userid): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gdlib.php');
        $iconid = process_new_icon(\context_user::instance($userid), 'user', 'icon', 0, __DIR__ . '/../pix/tiko.png');
        if ($iconid) {
            $DB->set_field('user', 'picture', $iconid, ['id' => $userid]);
        }
    }
}
