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
 * Decides in which courses AI Forum Assistant can be used: the site switch, plus the administrator's
 * optional restriction to some categories (subcategories included) and/or courses by short name.
 *
 * @package    local_aiforumassist
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class availability {
    /**
     * Whether the assistant can be used in a course.
     *
     * @param \stdClass $course Course record (id, shortname and category are used).
     * @return bool
     */
    public static function is_available_in_course(\stdClass $course): bool {
        if (!get_config('local_aiforumassist', 'enabled')) {
            return false;
        }
        $categoryids = self::get_allowed_category_ids();
        $shortnames = self::get_allowed_course_shortnames();
        if (!$categoryids && !$shortnames) {
            return true;
        }
        if (in_array(\core_text::strtolower(trim((string) ($course->shortname ?? ''))), $shortnames, true)) {
            return true;
        }
        if ($categoryids && !empty($course->category)) {
            $category = \core_course_category::get((int) $course->category, IGNORE_MISSING, true);
            if ($category) {
                // Path is "/<top>/<child>/<this>": the course's category and all its parents.
                $path = array_map('intval', explode('/', trim($category->path, '/')));
                if (array_intersect($path, $categoryids)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Ids of the categories the administrator allowed.
     *
     * @return int[] Empty when not restricted by category.
     */
    public static function get_allowed_category_ids(): array {
        $raw = (string) get_config('local_aiforumassist', 'allowedcategories');
        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }

    /**
     * Short names (lower-cased) of the courses the administrator allowed.
     *
     * @return string[] Empty when not restricted by course.
     */
    public static function get_allowed_course_shortnames(): array {
        $raw = (string) get_config('local_aiforumassist', 'allowedcourses');
        $names = array_map(fn($name) => \core_text::strtolower(trim($name)), preg_split('/[\r\n,]+/', $raw));
        return array_values(array_filter($names, fn($name) => $name !== ''));
    }

    /**
     * Choices for the "allowed categories" admin setting, loaded only when the settings page is shown.
     *
     * @return array Category id => category path name.
     */
    public static function get_category_choices(): array {
        return \core_course_category::make_categories_list();
    }
}
