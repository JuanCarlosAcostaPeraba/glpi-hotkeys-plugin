/**
 * GLPI Hotkeys Plugin - Client-side Shortcuts Engine
 *
 * @package GLPI Hotkeys Plugin
 * @author Juan Carlos Acosta Peraba
 * @license GPL-3.0-or-later
 */

(function() {
    'use strict';

    // Global submission lock and toast notification state
    const lockedForms = new WeakSet();
    let activeToast = null;
    let toastTimeout = null;

    /**
     * Load configuration from the meta tag.
     * @returns {object|null}
     */
    function loadConfig() {
        const meta = document.querySelector('meta[name="glpi-hotkeys-config"]');
        if (!meta) return null;
        try {
            return JSON.parse(meta.getAttribute('content'));
        } catch (e) {
            console.error('Failed to parse GLPI Hotkeys configuration', e);
            return null;
        }
    }

    /**
     * Check if an element is visible in the DOM.
     * @param {HTMLElement} el 
     * @returns {boolean}
     */
    function isVisible(el) {
        if (!el) return false;
        return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    }

    /**
     * Detect if a form is a supported Ticket form.
     * @param {HTMLFormElement} form 
     * @returns {boolean}
     */
    function isTicketForm(form) {
        if (!form || form.tagName !== 'FORM') return false;

        // Check itemtype hidden input
        const itemtypeInput = form.querySelector('input[name="itemtype"]');
        if (itemtypeInput && itemtypeInput.value === 'Ticket') {
            return true;
        }

        // Check action URL attribute
        const action = form.getAttribute('action') || '';
        if (action.includes('ticket.form.php')) {
            return true;
        }

        // Check form ID or class
        const id = form.getAttribute('id') || '';
        if (id.startsWith('ticketform') || id === 'form_ticket') {
            return true;
        }

        return false;
    }

    /**
     * Detect if a form is a supported Ticket Task form.
     * @param {HTMLFormElement} form 
     * @returns {boolean}
     */
    function isTicketTaskForm(form) {
        if (!form || form.tagName !== 'FORM') return false;

        // Check itemtype hidden input
        const itemtypeInput = form.querySelector('input[name="itemtype"]');
        if (itemtypeInput && (itemtypeInput.value === 'TicketTask' || itemtypeInput.value === 'ITILTask')) {
            return true;
        }

        // Check action URL attribute
        const action = form.getAttribute('action') || '';
        if (action.includes('tickettask.form.php') || action.includes('itiltask.form.php')) {
            return true;
        }

        // Fallback: Check if it has a ticket foreign key + task fields (e.g. inline form)
        const hasTicketsId = form.querySelector('input[name="tickets_id"]');
        const hasTaskFields = form.querySelector('[name*="task"]') || 
                              form.querySelector('input[name="itiltasks_id"]') || 
                              form.querySelector('input[name="tickettasks_id"]');
        
        if (hasTicketsId && hasTaskFields) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve all active, connected, and visible supported forms.
     * @returns {{ticketForms: HTMLFormElement[], taskForms: HTMLFormElement[]}}
     */
    function getSupportedForms() {
        const forms = Array.from(document.querySelectorAll('form'));
        const ticketForms = [];
        const taskForms = [];

        forms.forEach(form => {
            if (!form.isConnected) return;
            if (!isVisible(form)) return;

            if (isTicketTaskForm(form)) {
                taskForms.push(form);
            } else if (isTicketForm(form)) {
                ticketForms.push(form);
            }
        });

        return { ticketForms, taskForms };
    }

    /**
     * Resolve target form using the smart save priority rules.
     * @returns {{form: HTMLFormElement, type: string}|null}
     */
    function getTargetFormForSmartSave() {
        const activeEl = document.activeElement;
        const { ticketForms, taskForms } = getSupportedForms();

        // 1. Supported task form containing the currently focused element.
        if (activeEl) {
            const closestTaskForm = activeEl.closest('form');
            if (closestTaskForm && taskForms.includes(closestTaskForm)) {
                return { form: closestTaskForm, type: 'task' };
            }
        }

        // 2. Supported task form inside the topmost visible modal/dialog.
        const modals = Array.from(document.querySelectorAll('.modal, .ui-dialog, [role="dialog"]'))
            .filter(isVisible);

        if (modals.length > 0) {
            // Sort modals by z-index descending (topmost first)
            modals.sort((a, b) => {
                const zA = parseInt(window.getComputedStyle(a).zIndex) || 0;
                const zB = parseInt(window.getComputedStyle(b).zIndex) || 0;
                return zB - zA;
            });

            for (const modal of modals) {
                const taskFormInModal = taskForms.find(form => modal.contains(form));
                if (taskFormInModal) {
                    return { form: taskFormInModal, type: 'task' };
                }
            }
        }

        // 3. Other visible supported task form currently being edited.
        if (taskForms.length > 0) {
            return { form: taskForms[0], type: 'task' };
        }

        // 4. Supported ticket form containing the currently focused element.
        if (activeEl) {
            const closestTicketForm = activeEl.closest('form');
            if (closestTicketForm && ticketForms.includes(closestTicketForm)) {
                return { form: closestTicketForm, type: 'ticket' };
            }
        }

        // 5. Main visible supported ticket form.
        if (ticketForms.length > 0) {
            return { form: ticketForms[0], type: 'ticket' };
        }

        // 6. No action.
        return null;
    }

    /**
     * Resolve target form for force-saving the ticket.
     * @returns {{form: HTMLFormElement, type: string}|null}
     */
    function getTargetFormForForceSaveTicket() {
        const activeEl = document.activeElement;
        const { ticketForms } = getSupportedForms();

        // 1. Supported ticket form containing the currently focused element.
        if (activeEl) {
            const closestTicketForm = activeEl.closest('form');
            if (closestTicketForm && ticketForms.includes(closestTicketForm)) {
                return { form: closestTicketForm, type: 'ticket' };
            }
        }

        // 2. Main visible supported ticket form.
        if (ticketForms.length > 0) {
            return { form: ticketForms[0], type: 'ticket' };
        }

        return null;
    }

    /**
     * Find the appropriate submit button for a form.
     * @param {HTMLFormElement} form 
     * @returns {HTMLElement|null}
     */
    function findSubmitButton(form) {
        // Look for buttons with name="add" or name="update"
        let button = form.querySelector('button[name="add"], input[name="add"], button[name="update"], input[name="update"]');
        if (button) return button;

        // Look for any submit button
        button = form.querySelector('button[type="submit"], input[type="submit"]');
        if (button) return button;

        // Fallback: primary button class
        button = form.querySelector('.submit, .btn-primary');
        if (button) return button;

        return null;
    }

    /**
     * Match keyboard event against a configured shortcut.
     * @param {KeyboardEvent} e 
     * @param {object} shortcut 
     * @returns {boolean}
     */
    function matchShortcut(e, shortcut) {
        if (!shortcut) return false;

        const isMac = /Mac|iPod|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
        const eventCtrlOrMeta = isMac ? e.metaKey : e.ctrlKey;
        
        // Avoid triggering when both Ctrl and Cmd are pressed unless intentionally configured
        if (e.ctrlKey && e.metaKey) {
            return false;
        }

        const matchKey = e.key.toLowerCase() === shortcut.key.toLowerCase();
        const matchCtrl = !!eventCtrlOrMeta === !!shortcut.ctrlOrMeta;
        const matchAlt = !!e.altKey === !!shortcut.alt;
        const matchShift = !!e.shiftKey === !!shortcut.shift;

        return matchKey && matchCtrl && matchAlt && matchShift;
    }

    /**
     * Show non-blocking visual feedback toast.
     * @param {string} message 
     * @param {boolean} showSpinner 
     */
    function showToast(message, showSpinner = true) {
        hideToast();

        const toast = document.createElement('div');
        toast.className = 'glpi-hotkeys-toast';
        
        if (showSpinner) {
            const spinner = document.createElement('span');
            spinner.className = 'glpi-hotkeys-toast-spinner';
            toast.appendChild(spinner);
        }

        const text = document.createElement('span');
        text.textContent = message;
        toast.appendChild(text);

        document.body.appendChild(toast);
        activeToast = toast;

        // Force a reflow for transition
        toast.offsetHeight;
        toast.classList.add('show');

        // Automatically hide toast after 3 seconds as a fallback
        toastTimeout = setTimeout(hideToast, 3000);
    }

    /**
     * Hide the visual toast.
     */
    function hideToast() {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
            toastTimeout = null;
        }

        if (activeToast) {
            const toast = activeToast;
            activeToast = null;
            
            toast.classList.remove('show');
            // Remove from DOM after transition completes
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }
    }

    /**
     * Safely submit a form with validation, locking, and fallback handlers.
     * @param {HTMLFormElement} form 
     * @param {HTMLElement} submitButton 
     * @param {object} config 
     * @param {string} formType 'ticket' or 'task'
     * @returns {boolean} Whether submission was attempted
     */
    function submitForm(form, submitButton, config, formType) {
        if (lockedForms.has(form)) {
            return false;
        }

        // Native HTML5 validation check
        if (form.checkValidity && !form.checkValidity()) {
            // Trigger native validation UI bubble without locking the form
            if (form.reportValidity) {
                form.reportValidity();
            } else {
                form.requestSubmit(submitButton);
            }
            return false;
        }

        // Lock form to prevent double submission
        lockedForms.add(form);

        // Safety timeout to unlock in case requestSubmit fails silently or gets caught by external script
        const safetyUnlock = setTimeout(() => {
            lockedForms.delete(form);
            hideToast();
        }, 3000);

        // Listener to release lock if event is cancelled by other scripts (e.g. Ajax validations)
        const onSubmitEvent = (e) => {
            if (e.defaultPrevented) {
                lockedForms.delete(form);
                clearTimeout(safetyUnlock);
                hideToast();
            }
        };
        form.addEventListener('submit', onSubmitEvent, { once: true });

        // Show feedback
        if (config.feedback_enabled) {
            const msg = formType === 'task' 
                ? (config.locales?.saving_task || 'Saving task...') 
                : (config.locales?.saving_ticket || 'Saving ticket...');
            showToast(msg);
        }

        try {
            // Attempt modern HTML5 requestSubmit
            form.requestSubmit(submitButton);
        } catch (e) {
            // Fallback for older browsers or environment edge cases
            try {
                submitButton.click();
            } catch (clickErr) {
                form.submit();
            }
        }

        return true;
    }

    /**
     * Main event handler for global keydown events.
     * @param {KeyboardEvent} e 
     */
    function handleGlobalKeydown(e) {
        // Ignore repeated keydown events
        if (e.repeat) return;

        const config = GlpiHotkeys.loadConfig();
        if (!config) return;

        // 1. Process Smart Save
        if (config.smart_save_enabled && matchShortcut(e, config.smart_save_shortcut)) {
            const target = getTargetFormForSmartSave();
            if (target) {
                const btn = findSubmitButton(target.form);
                if (btn && !btn.disabled) {
                    e.preventDefault();
                    e.stopPropagation();
                    submitForm(target.form, btn, config, target.type);
                }
            }
            return;
        }

        // 2. Process Force Save Ticket
        if (config.force_save_enabled && matchShortcut(e, config.force_save_shortcut)) {
            const target = getTargetFormForForceSaveTicket();
            if (target) {
                const btn = findSubmitButton(target.form);
                if (btn && !btn.disabled) {
                    e.preventDefault();
                    e.stopPropagation();
                    submitForm(target.form, btn, config, target.type);
                }
            }
            return;
        }
    }

    // Expose APIs for unit tests and GLPI
    const GlpiHotkeys = {
        lockedForms,
        loadConfig,
        isVisible,
        isTicketForm,
        isTicketTaskForm,
        getSupportedForms,
        getTargetFormForSmartSave,
        getTargetFormForForceSaveTicket,
        findSubmitButton,
        matchShortcut,
        showToast,
        hideToast,
        submitForm,
        handleGlobalKeydown,
        init: function() {
            window.addEventListener('keydown', handleGlobalKeydown, true);
        }
    };

    if (typeof window !== 'undefined') {
        window.GlpiHotkeys = GlpiHotkeys;
        // Auto-initialize inside the browser
        document.addEventListener('DOMContentLoaded', () => {
            GlpiHotkeys.init();
        });
    }

    // Export module if running inside Node/Vitest test environment
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = GlpiHotkeys;
    }
})();
