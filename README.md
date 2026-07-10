# Hotkeys Plugin for GLPI

[![CI](https://github.com/JuanCarlosAcostaPeraba/glpi-hotkeys-plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/JuanCarlosAcostaPeraba/glpi-hotkeys-plugin/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)

Save GLPI ticket and ticket task forms using keyboard shortcuts. This plugin provides standard "Smart Save" and target "Force Save" operations, fully integrated with GLPI 11's core architecture and forms.

## Features

- **Smart Save (`Ctrl/Cmd + S`):** Intelligently saves the active form. If a ticket task form is active, it saves the task. Otherwise, it saves the ticket.
- **Force-Save Ticket (`Ctrl/Cmd + Alt + S`):** Directly targets the main ticket form to save changes, even if a task dialog is open.
- **Platform Native:** Automatically handles Mac `Cmd` key vs Windows/Linux `Ctrl` key.
- **Dynamic TIMELINE & AJAX Support:** Remains fully functional after AJAX transitions, inline additions, dialog popups, and timeline updates.
- **Admin Configuration Screen:** Configure switches, record combinations with a live keyboard listener, and toggle visual toast alerts.
- **Safe Submission Guard:** Prevents double submissions and holding keys, and respects HTML5 native browser validations.

## Supported Versions

- **GLPI:** `11.0.0` or higher
- **PHP:** `8.2` or higher
- **Browsers:** Chrome/Chromium, Firefox, Edge, Safari

---

## Installation

### Production (From Release Archive)
1. Download the latest release `glpi-hotkeys-plugin-1.0.0.zip` from the GitHub Releases tab.
2. Extract the archive. The extracted directory **must** be named `hotkeys`.
3. Upload the `hotkeys` folder to your GLPI installation folder under:
   ```text
   GLPI_ROOT/plugins/hotkeys
   ```
4. Log in to GLPI as an administrator and navigate to **Administration > Plugins**.
5. Install and activate **Hotkeys**.

### Development Installation
1. Clone the repository into your plugins directory as `hotkeys`:
   ```bash
   git clone https://github.com/JuanCarlosAcostaPeraba/glpi-hotkeys-plugin.git GLPI_ROOT/plugins/hotkeys
   ```
2. Navigate to the plugin directory and install Node.js dependencies:
   ```bash
   cd GLPI_ROOT/plugins/hotkeys
   npm install
   ```
3. Run the asset builder to compile assets:
   ```bash
   npm run build
   ```

---

## Configuration

Navigate to **Administration > Plugins** and click on **Hotkeys** configuration icon to configure:
1. **Enable/Disable Smart Save** and edit its shortcut keys.
2. **Enable/Disable Force-Save Ticket** and edit its shortcut keys.
3. **Enable/Disable Visual Feedback** toast notifications.
4. **Restore Defaults** at any time.

The Shortcut Recorder UI captures the next pressed key combination and validates it automatically to prevent browser collisions.

---

## Technical Details & Security Model

- **No Bypass of Permissions:** The plugin uses the standard browser DOM method `form.requestSubmit()` on the actual save/update buttons. Standard GLPI security tokens (CSRF) and permissions remain strictly enforced by the backend.
- **Data Protection:** No ticket content, task text, or ordinary typing is logged or transmitted. The plugin does not connect to external servers.
- **Uninstall Cleanliness:** Normal uninstallation fully removes settings from the `glpi_configs` database table and does not touch core GLPI tickets or task history.

---

## Testing

Run frontend JavaScript tests (Vitest + JSDOM):
```bash
npm test
```

Run backend PHP configuration tests:
```bash
php tests/php/run.php
```

---

## License

Distributed under the GNU General Public License v3 or later. See `LICENSE` for details.
