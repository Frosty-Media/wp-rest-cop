<?php

declare(strict_types=1);

namespace FrostyMedia\WpRestCop\Settings;

use Dwnload\WpSettingsApi\Api\PluginSettings;
use Dwnload\WpSettingsApi\Api\SettingField;
use Dwnload\WpSettingsApi\Api\SettingSection;
use Dwnload\WpSettingsApi\Settings\FieldManager;
use Dwnload\WpSettingsApi\Settings\FieldTypes;
use Dwnload\WpSettingsApi\Settings\SectionManager;
use Dwnload\WpSettingsApi\SettingsApiFactory;
use Dwnload\WpSettingsApi\WpSettingsApi;
use TheFrosty\WpUtilities\Plugin\AbstractContainerProvider;
use TheFrosty\WpUtilities\Plugin\Plugin;
use function array_unshift;
use function esc_attr__;
use function esc_html__;
use function menu_page_url;
use function sprintf;
use const DAY_IN_SECONDS;
use const MINUTE_IN_SECONDS;

/**
 * Class Settings
 * @package TheFrosty\WpLoginLocker\Settings
 */
class Settings extends AbstractContainerProvider
{

    public const string SETTINGS = self::PREFIX . 'settings';
    public const string SETTING_INTERVAL = 'interval';
    public const string SETTING_LIMIT = 'limit';
    public const string SETTING_ALLOW_RULES = 'allow_rules';
    public const string SETTING_DENY_RULES = 'deny_rules';
    public const int DEFAULT_INTERVAL = MINUTE_IN_SECONDS;
    public const int DEFAULT_LIMIT = MINUTE_IN_SECONDS;
    private const string PREFIX = 'wp_rest_cop_';
    private const string MENU_SLUG = 'wp-rest-cop-settings';

    /**
     * Creat the PluginSettings object.
     * @param Plugin $plugin
     * @return PluginSettings
     */
    public static function factory(Plugin $plugin): PluginSettings
    {
        return SettingsApiFactory::create([
            'domain' => 'wp-rest-cop',
            'file' => $plugin->getFile(),
            'menu-slug' => self::MENU_SLUG,
            'menu-title' => 'WP Rest Cop',
            'page-title' => 'WP Rest Cop Settings',
            'prefix' => self::PREFIX,
            'version' => get_plugin_data($plugin->getFile(), translate: false)['Version'],
        ]);
    }

    /**
     * Register our callback to the WP Settings API action hook
     */
    public function addHooks(): void
    {
        $this->addAction(WpSettingsApi::HOOK_INIT, [$this, 'init'], 10, 3);
        $this->addFilter('plugin_action_links_' . $this->getPlugin()->getBasename(), [$this, 'addSettingsLink']);
    }

    /**
     * Initiate our setting to the Section & Field Manager classes.
     *
     * SettingField requires the following settings (passes as an array or set explicitly):
     * [
     *  SettingField::NAME
     *  SettingField::LABEL
     *  SettingField::DESC
     *  SettingField::TYPE
     *  SettingField::SECTION_ID
     * ]
     *
     * @param SectionManager $section_manager
     * @param FieldManager $field_manager
     * @param WpSettingsApi $wp_settings_api
     * @see SettingField for additional options for each field passed to the output
     */
    protected function init(
        SectionManager $section_manager,
        FieldManager $field_manager,
        WpSettingsApi $wp_settings_api
    ): void {
        if (!$wp_settings_api->isCurrentMenuSlug(self::MENU_SLUG)) {
            return;
        }

        $section_id = $section_manager->addSection(
            new SettingSection([
                SettingSection::SECTION_ID => self::SETTINGS, // Unique section ID
                SettingSection::SECTION_TITLE => 'REST Cop Settings',
            ])
        );

        $field_manager->addField(
            new SettingField([
                SettingField::NAME => self::SETTING_INTERVAL,
                SettingField::LABEL => esc_html__('Interval', 'wp-rest-cop'),
                SettingField::DESC => esc_html__('The global rate limit interval (time in seconds).', 'wp-rest-cop'),
                SettingField::TYPE => FieldTypes::FIELD_TYPE_NUMBER,
                SettingField::DEFAULT => self::DEFAULT_INTERVAL,
                SettingField::ATTRIBUTES => [
                    'min' => -1,
                    'max' => DAY_IN_SECONDS,
                ],
                SettingField::SECTION_ID => $section_id,
            ])
        );

        $field_manager->addField(
            new SettingField([
                SettingField::NAME => self::SETTING_LIMIT,
                SettingField::LABEL => esc_html__('Limit', 'wp-rest-cop'),
                SettingField::DESC => esc_html__('The global rate limit (requests per interval).', 'wp-rest-cop'),
                SettingField::TYPE => FieldTypes::FIELD_TYPE_NUMBER,
                SettingField::DEFAULT => self::DEFAULT_LIMIT,
                SettingField::ATTRIBUTES => [
                    'min' => 1,
                    'max' => 100000,
                ],
                SettingField::SECTION_ID => $section_id,
            ])
        );

        $field_manager->addField(
            new SettingField([
                SettingField::NAME => self::SETTING_ALLOW_RULES,
                SettingField::LABEL => esc_html__('Allow', 'wp-rest-cop'),
                SettingField::DESC => esc_html__('The IPs to allow.', 'wp-rest-cop'),
                SettingField::TYPE => FieldTypes::FIELD_TYPE_TEXT_ARRAY,
                SettingField::DEFAULT => ['127.0.0.1', '::1'],
                SettingField::SECTION_ID => $section_id,
            ])
        );

        $field_manager->addField(
            new SettingField([
                SettingField::NAME => self::SETTING_DENY_RULES,
                SettingField::LABEL => esc_html__('Deny', 'wp-rest-cop'),
                SettingField::DESC => esc_html__('The IPs to deny.', 'wp-rest-cop'),
                SettingField::TYPE => FieldTypes::FIELD_TYPE_TEXT_ARRAY,
                SettingField::SECTION_ID => $section_id,
            ])
        );
    }

    /**
     * Add a settings page link to the plugin's settings page.
     * @param array $actions
     * @return array
     */
    protected function addSettingsLink(array $actions): array
    {
        array_unshift(
            $actions,
            sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                menu_page_url(self::MENU_SLUG, false),
                esc_attr__('Settings for WP REST Cop', 'wp-rest-cop'),
                esc_html__('Settings', 'default')
            )
        );

        return $actions;
    }
}
