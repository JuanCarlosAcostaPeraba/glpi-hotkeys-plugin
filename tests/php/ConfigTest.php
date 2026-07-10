<?php
declare(strict_types=1);

// Define global stubs first to ensure they are registered in the global namespace
namespace {
    if (!class_exists('Config')) {
        class Config {
            public static array $mockValues = [];
            public static function getConfigurationValues(string $context): array {
                return self::$mockValues;
            }
            public static function setConfigurationValues(string $context, array $values): void {
                self::$mockValues = array_merge(self::$mockValues, $values);
            }
            public static function resetMock(): void {
                self::$mockValues = [];
            }
        }
    }

    if (!class_exists('Session')) {
        class Session {
            public static array $messages = [];
            public static function addMessageAfterRedirect(string $msg, bool $display, int $type): void {
                self::$messages[] = ['msg' => $msg, 'display' => $display, 'type' => $type];
            }
            public static function resetMock(): void {
                self::$messages = [];
            }
        }
    }

    if (!function_exists('__')) {
        function __(string $str, string $domain): string {
            return $str;
        }
    }

    if (!defined('INFO')) {
        define('INFO', 0);
    }
    if (!defined('ERROR')) {
        define('ERROR', 1);
    }
}

// Define the namespace for the mock TemplateRenderer
namespace Glpi\Application\View {
    if (!class_exists('Glpi\Application\View\TemplateRenderer')) {
        class TemplateRenderer {
            private static ?TemplateRenderer $instance = null;
            public array $rendered = [];
            
            public static function getInstance(): self {
                if (self::$instance === null) {
                    self::$instance = new self();
                }
                return self::$instance;
            }
            
            public function display(string $template, array $data): void {
                $this->rendered[] = ['template' => $template, 'data' => $data];
            }
            
            public function resetMock(): void {
                $this->rendered = [];
            }
        }
    }
}

// Define our test class namespace
namespace GlpiPlugin\Hotkeys\Tests {
    use PHPUnit\Framework\TestCase;

    // Manually require Config class
    require_once dirname(dirname(__DIR__)) . '/src/Config.php';

    class ConfigTest extends TestCase {
        protected function setUp(): void {
            parent::setUp();
            \Config::resetMock();
            \Session::resetMock();
            \Glpi\Application\View\TemplateRenderer::getInstance()->resetMock();
        }

        public function testGetSafeConfigReturnsDefaultsWhenDatabaseIsEmpty(): void {
            $config = \GlpiPlugin\Hotkeys\Config::getSafeConfig();
            
            $this->assertEquals(1, $config['smart_save_enabled']);
            $this->assertEquals('s', $config['smart_save_shortcut']['key']);
            $this->assertTrue($config['smart_save_shortcut']['ctrlOrMeta']);
            $this->assertFalse($config['smart_save_shortcut']['alt']);
            
            $this->assertEquals(1, $config['force_save_enabled']);
            $this->assertEquals('s', $config['force_save_shortcut']['key']);
            $this->assertTrue($config['force_save_shortcut']['ctrlOrMeta']);
            $this->assertTrue($config['force_save_shortcut']['alt']);
            
            $this->assertEquals(1, $config['feedback_enabled']);
        }

        public function testValidateShortcutJsonAcceptsValidShortcuts(): void {
            $json = '{"key":"s","ctrlOrMeta":true,"alt":false,"shift":false}';
            $isValid = \GlpiPlugin\Hotkeys\Config::validateShortcutJson($json, $error);
            
            $this->assertTrue($isValid);
            $this->assertNull($error);
        }

        public function testValidateShortcutJsonRejectsMissingModifier(): void {
            $json = '{"key":"s","ctrlOrMeta":false,"alt":true,"shift":false}';
            $isValid = \GlpiPlugin\Hotkeys\Config::validateShortcutJson($json, $error);
            
            $this->assertFalse($isValid);
            $this->assertStringContainsString('Shortcut must require at least one modifier key', $error);
        }

        public function testValidateShortcutJsonRejectsModifierOnly(): void {
            $json = '{"key":"alt","ctrlOrMeta":true,"alt":true,"shift":false}';
            $isValid = \GlpiPlugin\Hotkeys\Config::validateShortcutJson($json, $error);
            
            $this->assertFalse($isValid);
            $this->assertStringContainsString('Modifier-only shortcuts are not allowed', $error);
        }

        public function testValidateShortcutJsonRejectsCtrlShiftS(): void {
            $json = '{"key":"s","ctrlOrMeta":true,"alt":false,"shift":true}';
            $isValid = \GlpiPlugin\Hotkeys\Config::validateShortcutJson($json, $error);
            
            $this->assertFalse($isValid);
            $this->assertStringContainsString('Ctrl/Cmd + Shift + S is blocked', $error);
        }

        public function testValidateShortcutJsonRejectsDangerousShortcuts(): void {
            $json = '{"key":"w","ctrlOrMeta":true,"alt":false,"shift":false}';
            $isValid = \GlpiPlugin\Hotkeys\Config::validateShortcutJson($json, $error);
            
            $this->assertFalse($isValid);
            $this->assertStringContainsString('shortcut is reserved or dangerous', $error);
        }

        public function testUpdateConfigSavesValidSettings(): void {
            $configObj = new \GlpiPlugin\Hotkeys\Config();
            $postData = [
                'smart_save_enabled' => '1',
                'smart_save_shortcut' => '{"key":"y","ctrlOrMeta":true,"alt":false,"shift":false}',
                'force_save_enabled' => '1',
                'force_save_shortcut' => '{"key":"y","ctrlOrMeta":true,"alt":true,"shift":false}',
                'feedback_enabled' => '1'
            ];

            $errors = $configObj->updateConfig($postData);
            $this->assertEmpty($errors);

            $saved = \GlpiPlugin\Hotkeys\Config::getSafeConfig();
            $this->assertEquals('y', $saved['smart_save_shortcut']['key']);
            $this->assertEquals('y', $saved['force_save_shortcut']['key']);
        }

        public function testUpdateConfigRejectsIdenticalShortcuts(): void {
            $configObj = new \GlpiPlugin\Hotkeys\Config();
            $postData = [
                'smart_save_enabled' => '1',
                'smart_save_shortcut' => '{"key":"s","ctrlOrMeta":true,"alt":false,"shift":false}',
                'force_save_enabled' => '1',
                'force_save_shortcut' => '{"key":"s","ctrlOrMeta":true,"alt":false,"shift":false}',
                'feedback_enabled' => '1'
            ];

            $errors = $configObj->updateConfig($postData);
            $this->assertNotEmpty($errors);
            $this->assertStringContainsString('shortcuts must be different', $errors[0]);
        }

        public function testRestoreDefaultsResetsSettings(): void {
            $configObj = new \GlpiPlugin\Hotkeys\Config();
            
            // Change config first
            \Config::setConfigurationValues('plugin:hotkeys', [
                'smart_save_shortcut' => '{"key":"x","ctrlOrMeta":true,"alt":false,"shift":false}'
            ]);
            
            $configObj->restoreDefaults();
            
            $restored = \GlpiPlugin\Hotkeys\Config::getSafeConfig();
            $this->assertEquals('s', $restored['smart_save_shortcut']['key']);
        }

        public function testShowFormRendersTwigTemplate(): void {
            $configObj = new \GlpiPlugin\Hotkeys\Config();
            $configObj->showForm();

            $renderer = \Glpi\Application\View\TemplateRenderer::getInstance();
            $this->assertCount(1, $renderer->rendered);
            $this->assertEquals('@hotkeys/config.html.twig', $renderer->rendered[0]['template']);
        }
    }
}
