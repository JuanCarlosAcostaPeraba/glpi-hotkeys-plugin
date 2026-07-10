# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-10

### Added
- Initial release of GLPI Hotkeys Plugin.
- Keyboard shortcut `Ctrl/Cmd + S` (Smart Save) to save active tickets and ticket tasks.
- Keyboard shortcut `Ctrl/Cmd + Alt + S` (Force-Save Ticket) to bypass open tasks and save the ticket.
- Admin configuration interface in `plugins/hotkeys/front/config.form.php` to enable/disable features, record key shortcuts, and configure visual feedback.
- Visual toast alerts on save operations.
- Dynamic layout updates support (AJAX reloads and timeline re-renderings).
- Client and server-side validation of key combinations.
