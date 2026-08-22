/**
 * Settings screen preview.
 *
 * Shows today's date in the selected calendar and digits as the controls
 * change. A separate file rather than an inline script block, which Plugin
 * Check rejects and a strict content security policy blocks.
 */

import {render} from './format.js';

/** Wire the preview to the settings controls. */
function start() {
    const settings = window.intlDateTimeCalendarAdmin || {};
    const output = document.getElementById('intl-preview-date');
    const calendar = document.getElementById('intl-calendar-type');
    const numbering = document.getElementById('intl-numbering-system');

    if (!output || !calendar) {
        return;
    }

    const update = () => {
        const format = settings.dateFormat || 'F j, Y';

        try {
            output.textContent = render(new Date(), format, {
                locale: settings.locale || 'en',
                calendar: calendar.value,
                numberingSystem: numbering ? numbering.value : '',
                timeZone: settings.timeZone || undefined,
            });
        } catch (e) {
            output.textContent = new Date().toLocaleDateString(settings.locale || 'en');
        }
    };

    calendar.addEventListener('change', update);
    if (numbering) {
        numbering.addEventListener('change', update);
    }

    update();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}
