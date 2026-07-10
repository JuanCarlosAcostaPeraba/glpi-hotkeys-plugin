# Contributing to GLPI Hotkeys Plugin

We welcome contributions from the community! To contribute to this project, please follow these guidelines.

## Development Environment Setup

1. **Prerequisites:**
   - PHP >= 8.2 (GLPI 11 minimum requirement)
   - Node.js >= 18 and npm

2. **Clone the Repository:**
   Place the cloned repository inside your GLPI `plugins/` directory:
   ```bash
   cd /path/to/glpi/plugins
   git clone https://github.com/JuanCarlosAcostaPeraba/glpi-hotkeys-plugin.git hotkeys
   ```

3. **Install Dependencies:**
   Run the following commands inside the plugin folder:
   ```bash
   npm install
   ```

## Running Tests

Before submitting a pull request, ensure all tests pass.

- **Run JavaScript Tests (Vitest):**
  ```bash
  npm test
  ```
- **Run PHP Tests (Custom runner):**
  ```bash
  php tests/php/run.php
  ```

## Code Quality and Minification

Always minify production assets when you change the source JavaScript:
```bash
npm run build
```
This updates `public/js/hotkeys.min.js`. The build will be checked in the CI pipeline.

## Pull Request Process

1. Fork the repository and create your branch from `main`.
2. Write tests covering your changes.
3. Ensure the minified files are synchronized (`npm run build`).
4. Commit your changes using conventional messages.
5. Submit a pull request and describe your changes.
