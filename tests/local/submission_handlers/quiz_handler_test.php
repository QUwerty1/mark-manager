<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests of the quiz submission handler.
 *
 * Test cases U27 - U29 of tests/README.md.
 *
 * @package    block_mark_manager
 * @category   test
 * @copyright  2026 Nikita Semenov <nikita.7nov@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_mark_manager\local\submission_handlers\quiz_handler
 */

namespace block_mark_manager\local\submission_handlers;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests of the quiz submission handler.
 */
class quiz_handler_test extends \advanced_testcase {
    /**
     * Calls the private method building the question preview.
     *
     * The method is private, so the tests call it through reflection and stay
     * independent from the database and from the mod_quiz data.
     *
     * @param string $questiontext Question text, possibly containing HTML.
     * @param int|null $maxlength Maximum preview length, null for the default one.
     * @return string Question preview.
     */
    protected function make_question_preview(string $questiontext, ?int $maxlength = null): string {
        $handler = new quiz_handler();
        $method  = new \ReflectionMethod(quiz_handler::class, 'make_question_preview');
        $method->setAccessible(true);

        if ($maxlength === null) {
            return $method->invoke($handler, $questiontext);
        }

        return $method->invoke($handler, $questiontext, $maxlength);
    }

    /**
     * U27: the whole text is returned when it is shorter than the limit.
     *
     * @covers ::make_question_preview
     */
    public function test_make_question_preview_returns_whole_short_text() {
        $this->resetAfterTest();

        $this->assertSame('Краткий вопрос', $this->make_question_preview('Краткий вопрос', 100));
    }

    /**
     * U28: a too long text is cut and gets an ellipsis, HTML tags are removed.
     *
     * @covers ::make_question_preview
     */
    public function test_make_question_preview_truncates_long_text() {
        $this->resetAfterTest();

        $longtext = '<p>' . str_repeat('А', 200) . '</p>';
        $preview  = $this->make_question_preview($longtext, 100);

        $this->assertSame(str_repeat('А', 100) . '…', $preview);
        $this->assertSame(100, \core_text::strlen(\core_text::substr($preview, 0, 100)));
        $this->assertStringNotContainsString('<p>', $preview);
    }

    /**
     * The default maximum length of the preview is 100 characters.
     *
     * @covers ::make_question_preview
     */
    public function test_make_question_preview_uses_default_maxlength() {
        $this->resetAfterTest();

        $preview = $this->make_question_preview(str_repeat('Б', 200));

        $this->assertSame(str_repeat('Б', 100) . '…', $preview);
    }

    /**
     * U29: a text consisting of HTML tags only results in an empty string.
     *
     * @covers ::make_question_preview
     */
    public function test_make_question_preview_returns_empty_string_for_html_only() {
        $this->resetAfterTest();

        $this->assertSame('', $this->make_question_preview('<p></p>', 100));
    }
}
