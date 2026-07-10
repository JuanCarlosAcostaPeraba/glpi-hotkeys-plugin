/**
 * GLPI Hotkeys Plugin - Javascript Unit Tests
 * @vitest-environment jsdom
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

// Import the hotkeys engine. Since it runs as IIFE, we require it in the JSDOM context.
let GlpiHotkeys;

describe('GLPI Hotkeys JS Engine', () => {
    let mockMeta;

    beforeEach(() => {
        // Setup JSDOM body
        document.body.innerHTML = '';
        
        // Mock offsetWidth/offsetHeight globally for JSDOM
        Object.defineProperty(HTMLElement.prototype, 'offsetWidth', {
            configurable: true,
            value: 100
        });
        Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
            configurable: true,
            value: 100
        });

        // Mock window.navigator.platform to test macOS vs others
        Object.defineProperty(window.navigator, 'platform', {
            value: 'Win32',
            configurable: true
        });

        // Set default configurations in a meta tag
        mockMeta = document.createElement('meta');
        mockMeta.setAttribute('name', 'glpi-hotkeys-config');
        mockMeta.setAttribute('content', JSON.stringify({
            smart_save_enabled: true,
            smart_save_shortcut: { key: 's', ctrlOrMeta: true, alt: false, shift: false },
            force_save_enabled: true,
            force_save_shortcut: { key: 's', ctrlOrMeta: true, alt: true, shift: false },
            feedback_enabled: true,
            locales: {
                saving_ticket: 'Saving ticket...',
                saving_task: 'Saving task...'
            }
        }));
        document.head.appendChild(mockMeta);

        // Load the hotkeys module
        // Reset modules cache to reload fresh instance
        vi.resetModules();
        GlpiHotkeys = require('../../public/js/hotkeys.js');
    });

    afterEach(() => {
        // Restore prototype definitions
        delete HTMLElement.prototype.offsetWidth;
        delete HTMLElement.prototype.offsetHeight;
        
        if (mockMeta && mockMeta.parentNode) {
            mockMeta.parentNode.removeChild(mockMeta);
        }
        document.body.innerHTML = '';
        vi.restoreAllMocks();
    });

    it('should correctly load the configuration from meta tag', () => {
        const config = GlpiHotkeys.loadConfig();
        expect(config).not.toBeNull();
        expect(config.smart_save_enabled).toBe(true);
        expect(config.smart_save_shortcut.key).toBe('s');
    });

    it('should match smart save Ctrl + S on Windows/Linux', () => {
        const config = GlpiHotkeys.loadConfig();
        const event = new KeyboardEvent('keydown', { key: 's', ctrlKey: true, metaKey: false });
        
        const matched = GlpiHotkeys.matchShortcut(event, config.smart_save_shortcut);
        expect(matched).toBe(true);
    });

    it('should match smart save Cmd + S on macOS', () => {
        Object.defineProperty(window.navigator, 'platform', {
            value: 'MacIntel',
            configurable: true
        });

        const config = GlpiHotkeys.loadConfig();
        const event = new KeyboardEvent('keydown', { key: 's', ctrlKey: false, metaKey: true });
        
        const matched = GlpiHotkeys.matchShortcut(event, config.smart_save_shortcut);
        expect(matched).toBe(true);
    });

    it('should match force ticket save Ctrl + Alt + S', () => {
        const config = GlpiHotkeys.loadConfig();
        const event = new KeyboardEvent('keydown', { key: 's', ctrlKey: true, altKey: true });
        
        const matched = GlpiHotkeys.matchShortcut(event, config.force_save_shortcut);
        expect(matched).toBe(true);
    });

    it('should ignore duplicate keys when event.repeat is true', () => {
        const eventHandlerSpy = vi.spyOn(GlpiHotkeys, 'handleGlobalKeydown');
        
        const event = new KeyboardEvent('keydown', { key: 's', ctrlKey: true, repeat: true });
        window.dispatchEvent(event);
        
        // Wait, handleGlobalKeydown will run, but should return early on event.repeat
        // We can test if loadConfig is called or not, since e.repeat check happens first.
        const loadConfigSpy = vi.spyOn(GlpiHotkeys, 'loadConfig');
        GlpiHotkeys.handleGlobalKeydown(event);
        expect(loadConfigSpy).not.toHaveBeenCalled();
    });

    it('should ignore unrelated keyboard shortcuts', () => {
        const config = GlpiHotkeys.loadConfig();
        const event = new KeyboardEvent('keydown', { key: 'k', ctrlKey: true });
        
        const matched = GlpiHotkeys.matchShortcut(event, config.smart_save_shortcut);
        expect(matched).toBe(false);
    });

    it('should correctly identify ticket forms', () => {
        const ticketForm = document.createElement('form');
        ticketForm.setAttribute('action', '/front/ticket.form.php');
        ticketForm.setAttribute('id', 'form_ticket');
        
        const genericForm = document.createElement('form');
        genericForm.setAttribute('action', '/front/computer.form.php');

        expect(GlpiHotkeys.isTicketForm(ticketForm)).toBe(true);
        expect(GlpiHotkeys.isTicketForm(genericForm)).toBe(false);
    });

    it('should correctly identify task forms', () => {
        const taskForm = document.createElement('form');
        taskForm.setAttribute('action', '/front/tickettask.form.php');
        
        const genericForm = document.createElement('form');
        genericForm.setAttribute('action', '/front/computer.form.php');

        expect(GlpiHotkeys.isTicketTaskForm(taskForm)).toBe(true);
        expect(GlpiHotkeys.isTicketTaskForm(genericForm)).toBe(false);
    });

    it('should respect smart save priority: task form inside modal takes precedence over ticket form', () => {
        // Create ticket form
        const ticketForm = document.createElement('form');
        ticketForm.setAttribute('action', '/front/ticket.form.php');
        ticketForm.setAttribute('id', 'form_ticket');
        ticketForm.style.width = '100px'; // make it visible in JSDOM
        ticketForm.style.height = '100px';
        document.body.appendChild(ticketForm);

        // Create modal with task form
        const modal = document.createElement('div');
        modal.className = 'modal show';
        modal.style.width = '100px';
        modal.style.height = '100px';
        
        const taskForm = document.createElement('form');
        taskForm.setAttribute('action', '/front/tickettask.form.php');
        taskForm.style.width = '100px';
        taskForm.style.height = '100px';
        modal.appendChild(taskForm);
        document.body.appendChild(modal);

        // Mock window getComputedStyle z-index
        vi.spyOn(window, 'getComputedStyle').mockImplementation((el) => {
            return { zIndex: el.classList.contains('modal') ? '1050' : '1' };
        });

        // Resolve smart save target
        const target = GlpiHotkeys.getTargetFormForSmartSave();
        expect(target).not.toBeNull();
        expect(target.type).toBe('task');
        expect(target.form).toBe(taskForm);
    });

    it('should respect force ticket save: targets ticket form even if a task form is visible', () => {
        const ticketForm = document.createElement('form');
        ticketForm.setAttribute('action', '/front/ticket.form.php');
        ticketForm.setAttribute('id', 'form_ticket');
        ticketForm.style.width = '100px';
        ticketForm.style.height = '100px';
        document.body.appendChild(ticketForm);

        const taskForm = document.createElement('form');
        taskForm.setAttribute('action', '/front/tickettask.form.php');
        taskForm.style.width = '100px';
        taskForm.style.height = '100px';
        document.body.appendChild(taskForm);

        const target = GlpiHotkeys.getTargetFormForForceSaveTicket();
        expect(target).not.toBeNull();
        expect(target.type).toBe('ticket');
        expect(target.form).toBe(ticketForm);
    });

    it('should ignore hidden forms', () => {
        const hiddenForm = document.createElement('form');
        hiddenForm.setAttribute('action', '/front/ticket.form.php');
        hiddenForm.setAttribute('id', 'form_ticket');
        document.body.appendChild(hiddenForm);

        // Override offsetWidth and offsetHeight to 0 to simulate hidden state
        Object.defineProperty(hiddenForm, 'offsetWidth', {
            configurable: true,
            value: 0
        });
        Object.defineProperty(hiddenForm, 'offsetHeight', {
            configurable: true,
            value: 0
        });

        const forms = GlpiHotkeys.getSupportedForms();
        expect(forms.ticketForms.length).toBe(0);
    });

    it('should prevent duplicate submissions using locking mechanism', () => {
        const form = document.createElement('form');
        form.setAttribute('action', '/front/ticket.form.php');
        form.setAttribute('id', 'form_ticket');
        form.style.width = '100px';
        form.style.height = '100px';
        
        const btn = document.createElement('button');
        btn.type = 'submit';
        form.appendChild(btn);
        document.body.appendChild(form);

        // Spy on form requestSubmit
        form.requestSubmit = vi.fn();

        const config = GlpiHotkeys.loadConfig();

        // First submit should work and lock the form
        const success1 = GlpiHotkeys.submitForm(form, btn, config, 'ticket');
        expect(success1).toBe(true);

        // Second submit should fail immediately due to lock
        const success2 = GlpiHotkeys.submitForm(form, btn, config, 'ticket');
        expect(success2).toBe(false);
    });

    it('should not lock form when HTML5 validation fails', () => {
        const form = document.createElement('form');
        form.setAttribute('action', '/front/ticket.form.php');
        form.setAttribute('id', 'form_ticket');
        form.style.width = '100px';
        form.style.height = '100px';
        
        const input = document.createElement('input');
        input.required = true;
        form.appendChild(input);

        const btn = document.createElement('button');
        btn.type = 'submit';
        form.appendChild(btn);
        document.body.appendChild(form);

        const config = GlpiHotkeys.loadConfig();

        // Submission should return false and NOT lock the form because of validation failure
        const submitted = GlpiHotkeys.submitForm(form, btn, config, 'ticket');
        expect(submitted).toBe(false);
        expect(GlpiHotkeys.lockedForms.has(form)).toBe(false);
    });

    it('should release lock if another submit event listener cancels submission', () => {
        const form = document.createElement('form');
        form.setAttribute('action', '/front/ticket.form.php');
        form.style.width = '100px';
        form.style.height = '100px';
        
        const btn = document.createElement('button');
        btn.type = 'submit';
        form.appendChild(btn);
        document.body.appendChild(form);

        // Add a listener that cancels the submit event
        form.addEventListener('submit', (e) => {
            e.preventDefault();
        });

        const config = GlpiHotkeys.loadConfig();

        // Dispatch key event or call submitForm directly
        GlpiHotkeys.submitForm(form, btn, config, 'ticket');

        // Since the event was prevented, submitForm listener should fire and delete the lock immediately
        // Wait, dispatching the submit event triggers listeners
        const event = new Event('submit', { cancelable: true });
        form.dispatchEvent(event);

        expect(GlpiHotkeys.lockedForms.has(form)).toBe(false);
    });

    it('should forward keydown from TinyMCE editor to handleGlobalKeydown', () => {
        // Mock global tinymce object
        window.tinymce = {
            editors: [],
            on: vi.fn((event, callback) => {
                if (event === 'AddEditor') {
                    window.tinymce._addEditorCallback = callback;
                }
            })
        };

        // Re-initialize hotkeys to bind tinymce
        GlpiHotkeys.init();

        // Create a mock form and textarea
        const taskForm = document.createElement('form');
        taskForm.setAttribute('action', '/front/tickettask.form.php');
        taskForm.style.width = '100px';
        taskForm.style.height = '100px';
        const textarea = document.createElement('textarea');
        taskForm.appendChild(textarea);
        const btn = document.createElement('button');
        btn.type = 'submit';
        taskForm.appendChild(btn);
        document.body.appendChild(taskForm);

        // Mock editor instance
        const mockEditor = {
            _glpiHotkeysBound: false,
            getElement: () => textarea,
            on: vi.fn((event, callback) => {
                if (event === 'keydown') {
                    mockEditor._keydownCallback = callback;
                }
            })
        };

        // Simulate AddEditor event
        if (window.tinymce._addEditorCallback) {
            window.tinymce._addEditorCallback({ editor: mockEditor });
        }

        // Verify keydown listener was bound to mock editor
        expect(mockEditor.on).toHaveBeenCalledWith('keydown', expect.any(Function));

        // Mock requestSubmit on taskForm to handle JSDOM missing implementation
        taskForm.requestSubmit = vi.fn();

        // Simulate keydown event inside TinyMCE
        const event = new KeyboardEvent('keydown', { key: 's', ctrlKey: true });
        mockEditor._keydownCallback(event);

        // Verify that requestSubmit was triggered on the parent task form
        expect(taskForm.requestSubmit).toHaveBeenCalled();
        
        // Clean up global tinymce mock
        delete window.tinymce;
    });

    describe('formatShortcut helper', () => {
        it('should format shortcuts for Windows/Linux', () => {
            Object.defineProperty(window.navigator, 'platform', {
                value: 'Win32',
                configurable: true
            });
            const formatted = GlpiHotkeys.formatShortcut({ key: 's', ctrlOrMeta: true, alt: true, shift: false });
            expect(formatted).toBe('Ctrl + Alt + S');
        });

        it('should format shortcuts for macOS', () => {
            Object.defineProperty(window.navigator, 'platform', {
                value: 'MacIntel',
                configurable: true
            });
            const formatted = GlpiHotkeys.formatShortcut({ key: 's', ctrlOrMeta: true, alt: true, shift: true });
            expect(formatted).toBe('⌘ + ⌥ + ⇧ + S');
        });
    });

    describe('updateHelpBadge behavior', () => {
        beforeEach(() => {
            const badge = document.querySelector('.glpi-hotkeys-help-badge');
            if (badge) badge.remove();
        });

        it('should render help badge when ticket form is present', () => {
            const form = document.createElement('form');
            form.setAttribute('action', '/front/ticket.form.php');
            form.setAttribute('id', 'form_ticket');
            document.body.appendChild(form);

            const config = {
                smart_save_enabled: 1,
                smart_save_shortcut: { key: 's', ctrlOrMeta: true, alt: false, shift: false },
                force_save_enabled: 1,
                force_save_shortcut: { key: 's', ctrlOrMeta: true, alt: true, shift: false },
                locales: { help_title: 'Keyboard Help' }
            };

            GlpiHotkeys.updateHelpBadge(config);

            const badge = document.querySelector('.glpi-hotkeys-help-badge');
            expect(badge).not.toBeNull();
            
            const title = badge.querySelector('.glpi-hotkeys-help-title');
            expect(title.textContent).toBe('Keyboard Help');

            form.remove();
        });

        it('should remove help badge when no forms are present', () => {
            // Render it first
            const form = document.createElement('form');
            form.setAttribute('action', '/front/ticket.form.php');
            form.setAttribute('id', 'form_ticket');
            document.body.appendChild(form);

            const config = {
                smart_save_enabled: 1,
                smart_save_shortcut: { key: 's', ctrlOrMeta: true, alt: false },
                locales: {}
            };

            GlpiHotkeys.updateHelpBadge(config);
            expect(document.querySelector('.glpi-hotkeys-help-badge')).not.toBeNull();

            // Remove form and run updateHelpBadge again
            form.remove();
            GlpiHotkeys.updateHelpBadge(config);

            expect(document.querySelector('.glpi-hotkeys-help-badge')).toBeNull();
        });

        it('should drag help badge and save coordinates to localStorage', () => {
            const form = document.createElement('form');
            form.setAttribute('action', '/front/ticket.form.php');
            form.setAttribute('id', 'form_ticket');
            document.body.appendChild(form);

            // Mock window properties
            window.innerWidth = 1000;
            window.innerHeight = 1000;

            const config = {
                smart_save_enabled: 1,
                smart_save_shortcut: { key: 's', ctrlOrMeta: true },
                locales: {}
            };

            GlpiHotkeys.updateHelpBadge(config);

            const badge = document.querySelector('.glpi-hotkeys-help-badge');
            expect(badge).not.toBeNull();

            // Simulate mousedown
            const mouseDownEvent = new MouseEvent('mousedown', {
                clientX: 500,
                clientY: 500,
                button: 0
            });
            badge.dispatchEvent(mouseDownEvent);

            // Simulate mousemove to drag it
            const mouseMoveEvent = new MouseEvent('mousemove', {
                clientX: 450,
                clientY: 470
            });
            document.dispatchEvent(mouseMoveEvent);

            // Simulate mouseup
            const mouseUpEvent = new MouseEvent('mouseup');
            document.dispatchEvent(mouseUpEvent);

            // Verify coordinates were stored in localStorage
            expect(localStorage.getItem('glpi-hotkeys-help-left')).not.toBeNull();
            expect(localStorage.getItem('glpi-hotkeys-help-top')).not.toBeNull();

            form.remove();
        });

        it('should dynamically add direction classes based on FAB coordinates', () => {
            const form = document.createElement('form');
            form.setAttribute('action', '/front/ticket.form.php');
            form.setAttribute('id', 'form_ticket');
            document.body.appendChild(form);

            const config = {
                smart_save_enabled: 1,
                smart_save_shortcut: { key: 's', ctrlOrMeta: true },
                locales: {}
            };

            // Case 1: Close to left and top boundaries (left = 100, top = 100)
            Object.defineProperty(HTMLElement.prototype, 'offsetLeft', { value: 100, configurable: true });
            Object.defineProperty(HTMLElement.prototype, 'offsetTop', { value: 100, configurable: true });

            GlpiHotkeys.updateHelpBadge(config);
            const badge = document.querySelector('.glpi-hotkeys-help-badge');
            expect(badge.classList.contains('pos-right-side')).toBe(true);
            expect(badge.classList.contains('pos-bottom-side')).toBe(true);

            // Cleanup badge properly via the API
            form.remove();
            GlpiHotkeys.updateHelpBadge(config); // Clears helpBadge and resets closure state

            // Re-create form for Case 2
            const form2 = document.createElement('form');
            form2.setAttribute('action', '/front/ticket.form.php');
            form2.setAttribute('id', 'form_ticket');
            document.body.appendChild(form2);

            // Case 2: Far from left and top boundaries (left = 500, top = 500)
            Object.defineProperty(HTMLElement.prototype, 'offsetLeft', { value: 500, configurable: true });
            Object.defineProperty(HTMLElement.prototype, 'offsetTop', { value: 500, configurable: true });

            GlpiHotkeys.updateHelpBadge(config);
            const badge2 = document.querySelector('.glpi-hotkeys-help-badge');
            expect(badge2).not.toBeNull();
            expect(badge2.classList.contains('pos-right-side')).toBe(false);
            expect(badge2.classList.contains('pos-bottom-side')).toBe(false);

            form2.remove();
        });

        it('should detect form visibility based on isFormVisible rules', () => {
            const form = document.createElement('form');
            document.body.appendChild(form);

            // Case 1: Form has layout dimensions
            Object.defineProperty(form, 'offsetWidth', { value: 100, configurable: true });
            Object.defineProperty(form, 'offsetHeight', { value: 100, configurable: true });
            expect(GlpiHotkeys.isFormVisible(form)).toBe(true);

            // Case 2: Form has 0 dimensions (e.g. display: contents) but has visible child input
            Object.defineProperty(form, 'offsetWidth', { value: 0, configurable: true });
            Object.defineProperty(form, 'offsetHeight', { value: 0, configurable: true });
            
            const input = document.createElement('input');
            form.appendChild(input);
            Object.defineProperty(input, 'offsetWidth', { value: 50, configurable: true });
            Object.defineProperty(input, 'offsetHeight', { value: 50, configurable: true });
            
            expect(GlpiHotkeys.isFormVisible(form)).toBe(true);

            // Case 3: Form has 0 dimensions and input has 0 dimensions (hidden)
            Object.defineProperty(input, 'offsetWidth', { value: 0, configurable: true });
            Object.defineProperty(input, 'offsetHeight', { value: 0, configurable: true });
            expect(GlpiHotkeys.isFormVisible(form)).toBe(false);

            form.remove();
        });

        it('should target ticket form under smart save only when dirty or focused', () => {
            const form = document.createElement('form');
            form.setAttribute('action', '/front/ticket.form.php');
            form.setAttribute('id', 'form_ticket');
            document.body.appendChild(form);

            // Case 1: Focus on body, form NOT dirty -> returns null
            Object.defineProperty(document, 'activeElement', { value: document.body, configurable: true });
            let target = GlpiHotkeys.getTargetFormForSmartSave();
            expect(target).toBeNull();

            // Case 2: Focus on body, form IS dirty -> returns ticket form
            form.dataset.dirty = 'true';
            target = GlpiHotkeys.getTargetFormForSmartSave();
            expect(target).not.toBeNull();
            expect(target.form).toBe(form);
            expect(target.type).toBe('ticket');

            // Case 3: Focus inside form, form NOT dirty -> returns ticket form
            delete form.dataset.dirty;
            const input = document.createElement('input');
            form.appendChild(input);
            Object.defineProperty(document, 'activeElement', { value: input, configurable: true });

            target = GlpiHotkeys.getTargetFormForSmartSave();
            expect(target).not.toBeNull();
            expect(target.form).toBe(form);
            expect(target.type).toBe('ticket');

            form.remove();
        });
    });
});
