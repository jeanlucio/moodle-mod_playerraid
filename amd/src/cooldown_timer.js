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

/**
 * Cooldown timer module for PlayerRaid.
 *
 * @module     mod_playerraid/cooldown_timer
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    return {
        /**
         * Initialize the cooldown timer countdowns.
         */
        init() {
            const cooldownSpans = document.querySelectorAll('.playerraid-cooldown-seconds');
            if (cooldownSpans.length === 0) {
                return;
            }

            cooldownSpans.forEach(span => {
                const expiry = parseInt(span.dataset.expiry, 10);
                if (isNaN(expiry) || expiry <= 0) {
                    return;
                }

                const interval = setInterval(() => {
                    const currentTime = Math.floor(Date.now() / 1000);
                    const remaining = Math.max(0, expiry - currentTime);

                    if (remaining > 0) {
                        span.textContent = remaining;
                    } else {
                        clearInterval(interval);
                        // Reload the page when cooldown expires to fetch the question.
                        window.location.reload();
                    }
                }, 1000);
            });
        }
    };
});
