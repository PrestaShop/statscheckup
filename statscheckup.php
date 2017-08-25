<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class statscheckup extends Module
{
    private $html = '';

    private $csvExport = array();

    public function __construct()
    {
        $this->name = 'statscheckup';
        $this->tab = 'analytics_stats';
        $this->version = '2.0.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->trans('Catalog evaluation', array(), 'Modules.Statscheckup.Admin');
        $this->description = $this->trans('Adds a quick evaluation of your catalog quality to the Stats dashboard.', array(), 'Modules.Statscheckup.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.3.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        $confs = $this->getConfigurationModule();
        // default checkup show
         $confs = array_merge($confs, array(
            'SHOW_DESCRIPTIONS' => true,
            'SHOW_SHORT_DESCRIPTIONS' => true,
            'SHOW_IMAGES' => true,
            'SHOW_SALES' => true,
            'SHOW_STOCK' => true,
            'SHOW_REFERENCE' => true,
            'SHOW_PRICE' => true,
        ));

        foreach ($confs as $confname => $confdefault) {
            if (!Configuration::get($confname)) {
                Configuration::updateValue($confname, (int)$confdefault);
            }
        }
        return (parent::install() && $this->registerHook('AdminStatsModules') && $this->registerHook('actionAdminControllerSetMedia'));
    }

    private function getConfigurationModule()
    {
        return array(
            'CHECKUP_DESCRIPTIONS_LT' => 100,
            'CHECKUP_DESCRIPTIONS_GT' => 400,
            'CHECKUP_SHORT_DESCRIPTIONS_LT' => 25,
            'CHECKUP_SHORT_DESCRIPTIONS_GT' => 100,
            'CHECKUP_META_TITLE_LT' => 5,
            'CHECKUP_META_TITLE_GT' => 20,
            'CHECKUP_META_DESCRIPTION_LT' => 10,
            'CHECKUP_META_DESCRIPTION_GT' => 80,
            'CHECKUP_IMAGES_LT' => 0,
            'CHECKUP_IMAGES_GT' => 1,
            'CHECKUP_SALES_LT' => 0,
            'CHECKUP_SALES_GT' => 1,
            'CHECKUP_STOCK_LT' => 0,
            'CHECKUP_STOCK_GT' => 1,
            'CHECKUP_ACCESSORY_LT' => 0,
            'CHECKUP_ACCESSORY_GT' => 1,
            'CHECKUP_CARRIERS_LT' => 0,
            'CHECKUP_CARRIERS_GT' => 1,
            'CHECKUP_FEATURES_LT' => 0,
            'CHECKUP_FEATURES_GT' => 1,
            'CHECKUP_TAGS_LT' => 0,
            'CHECKUP_TAGS_GT' => 1,
            'CHECKUP_REFERENCE' => true,
            'CHECKUP_PRICE' => true,
            'CHECKUP_WHOLESALE_PRICE' => true,
            'CHECKUP_SHIPPING_COST' => true,
            'CHECKUP_WIDTH' => true,
            'CHECKUP_HEIGHT' => true,
            'CHECKUP_DEPTH' => true,
            'CHECKUP_WEIGHT' => true,
            'CHECKUP_ISBN' => true,
            'CHECKUP_EAN13' => true,
            'CHECKUP_UPC' => true,
            'CHECKUP_BRAND' => true,
            'CHECKUP_SUPPLIER' => true,
            'CHECKUP_CATEGORY' => true,
            'CHECKUP_AVAILABLE_ORDER' => true,
            'CHECKUP_ONLINE_ONLY' => true,
        );
    }

    public function hookActionAdminControllerSetMedia()
    {
        $this->context->controller->addCSS($this->_path.'css/statscheckup.css', 'all');
        $this->context->controller->addJS($this->_path.'js/statscheckup.js', 'all');
    }

    public function hookAdminStatsModules()
    {
        $displayConfirmation = $this->handlePostProcess();

        if (!isset($this->context->cookie->checkup_order)) {
            $this->context->cookie->checkup_order = 1;
        }

        $array_colors = $this->getArrayColor();

        $result = $this->getProductStats();
        if (!$result) {
            return $this->trans('No product was found.', array(), 'Modules.Statscheckup.Admin');
        }

        $array_conf = $this->initConfigurationEvaluation();

        $this->html = '<div class="panel-heading">'.$this->displayName.'</div>';
        $this->html .= $displayConfirmation;
        $this->html .= $this->showConfigurationForm($array_conf, $array_colors);
        $this->html .= $this->showOrderForm($array_conf);
        $this->html .= $this->showResult($result, $array_conf);

        return $this->html;
    }

    /**
     * Do submit form
     *
     * @return string
     */
    private function handlePostProcess()
    {
        $displayConfirmation = '';

        if (Tools::isSubmit('submitCheckup')) {
            $confs = $this->getConfigurationModule();
            foreach ($confs as $confname => $confdefault) {
                if (isset($_POST[$confname])) {
                    Configuration::updateValue($confname, (int)Tools::getValue($confname));
                }
            }

            // update show_ configuration
            $resetShow = $this->initConfigurationEvaluation();
            $shows = Tools::getValue('show_configuration');
            foreach ($resetShow as $key => $name) {
                $key = 'SHOW_'.$key;
                Configuration::updateValue($key, (bool)!empty($shows[$key]));
            }
            $displayConfirmation .= $this->displayConfirmation($this->trans('The settings have been updated.', array(), 'Admin.Notifications.Success'));
        }

        if (Tools::isSubmit('submitCheckupOrder')) {
            $this->context->cookie->checkup_order = (int)Tools::getValue('submitCheckupOrder');
            $displayConfirmation .= $this->displayConfirmation($this->trans('The settings have been updated.', array(), 'Admin.Notifications.Success'));
        }

        return $displayConfirmation;
    }

    /**
     * @param $array_conf <=> $this->initConfigurationEvaluation()
     * @return array
     */
    private function initTotalsEvaluation($array_conf)
    {
        $totals = array(
            'products' => 0,
            'active' => 0
        );

        $languages = $this->getLanguages();

        foreach ($languages as $language) {
            foreach ($array_conf as $conf) {
                if (isset($conf['language'])) {
                    $totals[$conf['table']['target'].'_'.$language['iso_code']] = 0;
                }
            }
        }

        foreach ($array_conf as $conf) {
            if (!isset($conf['language'])) {
                $totals[$conf['table']['target']] = 0;
            }
        }

        return $totals;
    }

    /**
     * @return array
     */
    private function initConfigurationEvaluation()
    {
        $array_conf = array(
            'DESCRIPTIONS' => array(
                'name' => $this->trans('Description', array(), 'Admin.Catalog.Feature'),
                'text' => $this->trans('chars (without HTML)', array(), 'Modules.Statscheckup.Admin'),
                'language' => true,
                'table' => array(
                    'target' => 'description',
                    'title' => $this->trans('Desc.', array(), 'Modules.Statscheckup.Admin'),
                    'countable' => true,
                )
            ),
            'SHORT_DESCRIPTIONS' => array(
                'name' => $this->trans('Summary', array(), 'Admin.Catalog.Feature'),
                'text' => $this->trans('chars (without HTML)', array(), 'Modules.Statscheckup.Admin'),
                'language' => true,
                'table' => array(
                    'target' => 'description_short',
                    'title' => $this->trans('Short desc.', array(), 'Modules.Statscheckup.Admin'),
                    'countable' => true,
                )
            ),
            'IMAGES' => array(
                'name' => $this->trans('Images', array(), 'Admin.Catalog.Feature'),
                'text' => $this->trans('images', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'images',
                    'title' => $this->trans('Images', array(), 'Admin.Catalog.Feature'),
                    'countable' => true,
                )
            ),
            'SALES' => array(
                'name' => $this->trans('Sales', array(), 'Admin.Global'),
                'text' => $this->trans('orders / month', array(), 'Modules.Statscheckup.Admin'),
                'countable' => true,
                'table' => array(
                    'target' => 'sales',
                    'title' => $this->trans('Sales', array(), 'Admin.Global'),
                    'countable' => true,
                )
            ),
            'STOCK' => array(
                'name' => $this->trans('Available quantity for sale', array(), 'Admin.Global'),
                'text' => strtolower($this->trans('Quantity', array(), 'Admin.Global')),
                'table' => array(
                    'target' => 'stock',
                    'title' => $this->trans('Available quantity for sale', array(), 'Admin.Global'),
                    'countable' => true,
                )
            ),
            'REFERENCE' => array(
                'type' => 'switch',
                'name' => $this->trans('Reference', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'reference',
                    'title' => $this->trans('Reference', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'PRICE' => array(
                'type' => 'switch',
                'name' => $this->trans('Price', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'price',
                    'title' => $this->trans('Price', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'META_TITLE' => array(
                'name' => $this->trans('Meta title', array(), 'Admin.Catalog.Feature'),
                'text' => $this->trans('chars (without HTML)', array(), 'Modules.Statscheckup.Admin'),
                'language' => true,
                'table' => array(
                    'target' => 'meta_title',
                    'title' => $this->trans('Meta title.', array(), 'Admin.Catalog.Feature'),
                    'countable' => true,
                )
            ),
            'META_DESCRIPTION' => array(
                'name' => $this->trans('Meta description', array(), 'Admin.Catalog.Feature'),
                'text' => $this->trans('chars (without HTML)', array(), 'Modules.Statscheckup.Admin'),
                'language' => true,
                'table' => array(
                    'target' => 'meta_description',
                    'title' => $this->trans('Meta description', array(), 'Admin.Catalog.Feature'),
                    'countable' => true,
                )
            ),
            'FEATURES' => array(
                'name' => $this->trans('Features', array(), 'Admin.Catalog.Feature'),
                'text' => $this->trans('Features', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'features',
                    'title' => $this->trans('Features', array(), 'Admin.Catalog.Feature'),
                    'countable' => true,
                )
            ),
            'TAGS' => array(
                'name' => $this->trans('Tags', array(), 'Admin.Catalog.Feature'),
                'text' => $this->trans('Tags', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'tags',
                    'title' => $this->trans('Tags', array(), 'Admin.Catalog.Feature'),
                    'countable' => true,
                )
            ),
            'ACCESSORY' => array(
                'name' => $this->trans('Related product', array(), 'Admin.Catalog.Feature'),
                'text' => $this->trans('Related product', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'accessory',
                    'title' => $this->trans('Related product', array(), 'Admin.Catalog.Feature'),
                    'countable' => true,
                )
            ),
            'CARRIERS' => array(
                'name' => $this->trans('Carrier', array(), 'Admin.Global'),
                'text' => $this->trans('Carrier', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'carrier',
                    'title' => $this->trans('Carrier', array(), 'Admin.Global'),
                    'countable' => true,
                )
            ),

            'WHOLESALE_PRICE' => array(
                'type' => 'switch',
                'name' => $this->trans('Wholesale price', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'wholesale_price',
                    'title' => $this->trans('Wholesale price', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'SHIPPING_COST' => array(
                'type' => 'switch',
                'name' => $this->trans('Shipping Costs', array(), 'Admin.Orderscustomers.Feature'),
                'table' => array(
                    'target' => 'shipping',
                    'title' => $this->trans('Shipping Costs', array(), 'Admin.Orderscustomers.Feature'),
                )
            ),
            'BRAND' => array(
                'type' => 'switch',
                'name' => $this->trans('Brand', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'brand',
                    'title' => $this->trans('Brand', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'SUPPLIER' => array(
                'type' => 'switch',
                'name' => $this->trans('Supplier', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'supplier',
                    'title' => $this->trans('Supplier', array(), 'Admin.Global'),
                )
            ),
            'CATEGORY' => array(
                'type' => 'switch',
                'name' => $this->trans('Default category', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'category',
                    'title' => $this->trans('Default category', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'WIDTH' => array(
                'type' => 'switch',
                'name' => $this->trans('Width', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'width',
                    'title' => $this->trans('Width', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'HEIGHT' => array(
                'type' => 'switch',
                'name' => $this->trans('Height', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'height',
                    'title' => $this->trans('Height', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'DEPTH' => array(
                'type' => 'switch',
                'name' => $this->trans('Depth', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'depth',
                    'title' => $this->trans('Depth', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'WEIGHT' => array(
                'type' => 'switch',
                'name' => $this->trans('Weight', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'weight',
                    'title' => $this->trans('Weight', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'ISBN' => array(
                'type' => 'switch',
                'name' => $this->trans('ISBN', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'isbn',
                    'title' => $this->trans('ISBN', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'EAN13' => array(
                'type' => 'switch',
                'name' => $this->trans('EAN 13', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'ean13',
                    'title' => $this->trans('EAN 13', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'UPC' => array(
                'type' => 'switch',
                'name' => $this->trans('UPC', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'upc',
                    'title' => $this->trans('UPC', array(), 'Admin.Catalog.Feature'),
                )
            ),
            'ONLINE_ONLY' => array(
                'type' => 'switch',
                'name' => $this->trans('Online only', array(), 'Shop.Theme.Catalog'),
                'table' => array(
                    'target' => 'online_only',
                    'title' => $this->trans('Online only', array(), 'Shop.Theme.Catalog'),
                )
            ),
            'AVAILABLE_ORDER' => array(
                'type' => 'switch',
                'name' => $this->trans('Available for order', array(), 'Admin.Catalog.Feature'),
                'table' => array(
                    'target' => 'available_for_order',
                    'title' => $this->trans('Available for order', array(), 'Admin.Catalog.Feature'),
                )
            ),
        );

        foreach ($array_conf as $key => &$conf) {
            $conf['table']['show'] = Configuration::get('SHOW_'.$key);
        }

        return $array_conf;
    }

    /**
     * Get languages active
     *
     * @return array
     */
    private function getLanguages()
    {
        return Language::getLanguages(true);
    }

    /**
     * @return array
     */
    private function getArrayColor()
    {
        return array(
            0 => '<img src="../modules/'.$this->name.'/img/red.png" alt="'.$this->trans('Bad', array(), 'Modules.Statscheckup.Admin').'" />',
            1 => '<img src="../modules/'.$this->name.'/img/orange.png" alt="'.$this->trans('Average', array(), 'Modules.Statscheckup.Admin').'" />',
            2 => '<img src="../modules/'.$this->name.'/img/green.png" alt="'.$this->trans('Good', array(), 'Modules.Statscheckup.Admin').'" />'
        );
    }

    /**
     * @return array
     */
    private function getArrayColorInfo()
    {
        return array(
            0 => array(
                'image' => '../modules/'.$this->name.'/img/red.png',
                'text' => ucfirst($this->trans('Bad', array(), 'Modules.Statscheckup.Admin')),
            ),
            1 => array(
                'image' => '../modules/'.$this->name.'/img/orange.png',
                'text' => ucfirst($this->trans('Average', array(), 'Modules.Statscheckup.Admin')),
            ),
            2 => array(
                'image' => '../modules/'.$this->name.'/img/green.png',
                'text' => ucfirst($this->trans('Good', array(), 'Modules.Statscheckup.Admin')),
            ),
        );
    }

    /**
     * @return array|false|mysqli_result|null|PDOStatement|resource
     */
    private function getProductStats()
    {
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        $order_by = 'p.id_product';
        if ($this->context->cookie->checkup_order == 2) {
            $order_by = 'pl.name';
        } elseif ($this->context->cookie->checkup_order == 3) {
            $order_by = 'sales DESC';
        }

        $sql = 'SELECT p.id_product, p.reference, product_shop.active, pl.name, 
            p.price, p.wholesale_price, p.width, p.height, p.depth, p.weight, 
            p.isbn, p.ean13, p.upc,
            p.id_manufacturer as brand, p.id_supplier as supplier, p.id_category_default as category,
            p.additional_shipping_cost as shipping,
            p.online_only, p.available_for_order,
            (
                SELECT COUNT(*)
                FROM '._DB_PREFIX_.'image i
                '.Shop::addSqlAssociation('image', 'i').'
                WHERE i.id_product = p.id_product
            ) as images, 
            (
                SELECT COUNT(*)
                FROM '._DB_PREFIX_.'accessory a
                WHERE a.id_product_1 = p.id_product
            ) as accessory,
            (
                SELECT COUNT(*)
                FROM '._DB_PREFIX_.'product_carrier pc
                WHERE pc.id_product = p.id_product
            ) as carrier,
            (
                SELECT COUNT(*)
                FROM '._DB_PREFIX_.'feature_product fp
                WHERE fp.id_product = p.id_product
            ) as features, 
            (
                SELECT COUNT(*)
                FROM '._DB_PREFIX_.'product_tag t
                WHERE t.id_product = p.id_product
            ) as tags, 
            (
                SELECT SUM(od.product_quantity)
                FROM '._DB_PREFIX_.'orders o
                LEFT JOIN '._DB_PREFIX_.'order_detail od ON o.id_order = od.id_order
                WHERE od.product_id = p.id_product
                    AND o.invoice_date BETWEEN '.ModuleGraph::getDateBetween().'
                    '.Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o').'
            ) as sales,
            IFNULL(stock.quantity, 0) as stock
            FROM '._DB_PREFIX_.'product p
            '.Shop::addSqlAssociation('product', 'p').'
            '.Product::sqlStock('p', 0).'
            LEFT JOIN '._DB_PREFIX_.'product_lang pl
                ON (p.id_product = pl.id_product AND pl.id_lang = '.(int)$this->context->language->id.Shop::addSqlRestrictionOnLang('pl').')
            WHERE state = 1
            ORDER BY '.$order_by;

        return $db->executeS($sql);
    }

    /**
     * Show the form to change the configuration
     *
     * @param $array_conf <=> $this->initConfigurationEvaluation()
     * @param $array_colors <=> $this->getArrayColor()
     *
     * @return string
     */
    private function showConfigurationForm($array_conf, $array_colors)
    {
        $return = '<form action="'.Tools::safeOutput(AdminController::$currentIndex.'&token='.Tools::getValue('token').'&module='.$this->name).'" method="post" class="row checkup form-horizontal">
        <a class="btn btn-default pull-right checkup-configuration" href="#" 
            data-expand="'.$this->trans('Expand All', array(), 'Admin.Actions').'" 
            data-collapse="'.$this->trans('Collapse All', array(), 'Admin.Actions').'">
            <i class="icon-extend"></i> '.$this->trans('Expand All', array(), 'Admin.Actions').'
        </a>
        <table class="table checkup">
            <thead>
                <tr>
                    <th>'.$this->trans('Visibility', array(), 'Modules.Statscheckup.Admin').'</th>
                    <th></th>
                    <th><span class="title_box active">'.$array_colors[0].' '.$this->trans('Not enough', array(), 'Modules.Statscheckup.Admin').'</span></th>
                    <th><span class="title_box active">'.$array_colors[2].' '.$this->trans('Alright', array(), 'Modules.Statscheckup.Admin').'</span>
                </tr>
            </thead>
            <tbody>';

        foreach ($array_conf as $conf => $translations) {
            $return .= '<tr ' . (empty($translations['table']['show']) ? 'class="grised-td hidden"' : '') . '>
                    <td>
                        <input name="show_configuration[SHOW_'.$conf.']" value="true" type="checkbox" ' . (empty($translations['table']['show']) ? '' : 'checked="checked"') . ' class="toggle-show pointer" data-target="'.$translations['table']['target'].'">
                    </td>
                    <td>
                        <label class="control-label col-lg-12">'.$translations['name'].'</label>
                    </td>
                    <td>';

            if (!isset($translations['type']) || $translations['type'] !== 'switch') {
                $return .= '<div class="row">
                    <div class="col-lg-11 input-group">
                        <span class="input-group-addon">' . $this->trans('Less than', array(), 'Modules.Statscheckup.Admin') . '</span>
                        <input ' . (empty($translations['table']['show']) ? 'disabled' : '') . ' type="text" name="CHECKUP_' . $conf . '_LT" value="' . Tools::safeOutput(Tools::getValue('CHECKUP_' . $conf . '_LT', Configuration::get('CHECKUP_' . $conf . '_LT'))) . '" />
                        <span class="input-group-addon">' . $translations['text'] . '</span>
                    </div>
                </div>';
            }

            $return .= '</td>
                        <td>';

            if (isset($translations['type']) && $translations['type'] === 'switch') {
                $return .= '<span class="switch prestashop-switch fixed-width-lg">
                    <input ' . (empty($translations['table']['show']) ? 'disabled' : '') . ' type="radio" name="CHECKUP_' . $conf . '" '.
    (Tools::getValue('CHECKUP_' . $conf, Configuration::get('CHECKUP_' . $conf)) === '1' ? 'checked="checked"': '').
    ' value="1" id="CHECKUP_' . $conf . '_on" >
                    <label for="CHECKUP_' . $conf . '_on" class="radioCheck">'.$this->trans('Yes', array(), 'Admin.Global').'</label>

                    <input ' . (empty($translations['table']['show']) ? 'disabled' : '') . ' type="radio" name="CHECKUP_' . $conf . '" '.
    (Tools::getValue('CHECKUP_' . $conf, Configuration::get('CHECKUP_' . $conf)) === '0' ? 'checked="checked"': '').
    ' value="0" id="CHECKUP_' . $conf . '_off" >
                    <label for="CHECKUP_' . $conf . '_off" class="radioCheck">'.$this->trans('No', array(), 'Admin.Global').'</label>
                    <a class="slide-button btn"></a>
                </span>';
            } else {
                $return .= '<div class="row">
                    <div class="col-lg-12 input-group">
                        <span class="input-group-addon">' . $this->trans('Greater than', array(), 'Modules.Statscheckup.Admin') . '</span>
                        <input ' . (empty($translations['table']['show']) ? 'disabled' : '') . ' type="text" name="CHECKUP_' . $conf . '_GT" value="' . Tools::safeOutput(Tools::getValue('CHECKUP_' . $conf . '_GT', Configuration::get('CHECKUP_' . $conf . '_GT'))) . '" />
                        <span class="input-group-addon">' . $translations['text'] . '</span>
                     </div>
                 </div>';
            }

            $return .= '</td>
                </tr>';
        }

        $return .= '
                </tbody>
            </table>
			<button type="submit" name="submitCheckup" class="btn btn-default pull-right">
				<i class="icon-save"></i> '.$this->trans('Save', array(), 'Admin.Actions').'
            </button>
		</form>';

        return $return;
    }

    /**
     * Show the select to change the order form
     *
     * @param $array_conf
     * @return string
     */
    private function showOrderForm($array_conf)
    {
        $return = '<div class="row" id="checkup-filters">
            <form action="'.Tools::safeOutput(AdminController::$currentIndex.'&token='.Tools::getValue('token').'&module='.$this->name).'" method="post" class="form-horizontal">		
                <div class="col-lg-4">
                    <label class="control-label pull-left">'.$this->trans('Order by', array(), 'Admin.Actions').'</label>          
                    <div class="col-lg-6">
                        <select name="submitCheckupOrder" onchange="this.form.submit();">
                            <option value="1">'.$this->trans('ID', array(), 'Admin.Global').'</option>
                            <option value="2" '.($this->context->cookie->checkup_order == 2 ? 'selected="selected"' : '').'>'.$this->trans('Name', array(), 'Admin.Global').'</option>
                            <option value="3" '.($this->context->cookie->checkup_order == 3 ? 'selected="selected"' : '').'>'.$this->trans('Sales', array(), 'Admin.Global').'</option>
                        </select>
                    </div>
                </div>';

                $array_color = $this->getArrayColorInfo();

                $return .= '<div class="col-lg-8">
                    <label class="control-label pull-left">'.$this->trans('Filter by', array(), 'Modules.Statscheckup.Admin').'</label>
                    <div class="col-lg-4">
                        <select name="change-filter-type" class="change-filter">';
                            foreach ($array_conf as $conf) {
                                $return .= '<option value="'.$conf['table']['target'].'">'.ucfirst($conf['name']).'</option>';
                            }

                        $return .= '</select>
                    </div>
                    <div class="col-lg-4">
                        <select name="change-filter-evaluation" class="change-filter">
                            <option value="-1">'.$this->trans('-- Choose evaluation --', array(), 'Modules.Statscheckup.Admin').'</option>';
                            foreach ($array_color as $k => $color) {
                                $return .= '<option value="'.$k.'">'.ucfirst($color['text']).'</option>';
                            }

                        $return .= '</select>
                    </div>
                    <div class="col-lg34">
                        <a class="btn btn-default export-csv pull-right" href="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'&export=1">
                            <i class="icon-cloud-upload"></i> '.$this->trans('CSV Export', array(), 'Admin.Global').'
                        </a>
                    </div>
                </div>
            </form>
        </div>';

        return $return;
    }

    /**
     * Construct column title for the table
     *
     * @param $array_conf
     * @return string
     */
    private function showColumnTitle($array_conf)
    {
        $languages = $this->getLanguages();

        $columnTitle = '
            <th><span class="title_box active">'.$this->trans('ID', array(), 'Admin.Global').'</span></th>
            <th><span class="title_box active">'.$this->trans('Item', array(), 'Admin.Global').'</span></th>
            <th class="center"><span class="title_box active">'.$this->trans('Active', array(), 'Admin.Global').'</span></th>';

        $this->csvExport['head'] = array(
            $this->trans('ID', array(), 'Admin.Global'),
            $this->trans('Item', array(), 'Admin.Global'),
            $this->trans('Active', array(), 'Admin.Global'),
        );

        foreach ($languages as $language) {
            foreach ($array_conf as $conf) {
                if (isset($conf['language'])) {
                    if (!empty($conf['table']['show'])) {
                        $this->csvExport['head'][] = $conf['table']['title'];
                    }

                    $columnTitle .= '<th class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">
                        <span class="title_box active">'.$conf['table']['title'].' ('.Tools::strtoupper($language['iso_code']).')</span>
                    </th>';
                }
            }
        }

        foreach ($array_conf as $conf) {
            if (!isset($conf['language'])) {
                if (!empty($conf['table']['show'])) {
                    $this->csvExport['head'][] = $conf['table']['title'];
                }

                $columnTitle .= '<th class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">
                    <span class="title_box active">'.$conf['table']['title'].'</span>
                </th>';
            }
        }

        $columnTitle .= '<th class="center"><span class="title_box active">'.$this->trans('Global', array(), 'Modules.Statscheckup.Admin').'</span></th>';

        $this->csvExport['head'][] = $this->trans('Global', array(), 'Modules.Statscheckup.Admin');

        return $columnTitle;
    }

    private function showResult($result, $array_conf)
    {
        $languages = $this->getLanguages();

        $token_products = Tools::getAdminToken('AdminProducts'.(int)Tab::getIdFromClassName('AdminProducts').(int)Context::getContext()->employee->id);

        $array_colors = $this->getArrayColor();
        $array_colors_info = $this->getArrayColorInfo();

        $totals = $this->initTotalsEvaluation($array_conf);

        $columnTitle = $this->showColumnTitle($array_conf);

        $return = '<div style="overflow-x:auto">
		<table class="table checkup2">
			<thead>
				<tr>'.
                    $columnTitle.'
				</tr>
			</thead>
			<tbody>';

        foreach ($result as $row) {
            $totals['products']++;

            $scores = $this->calcScoresProduct($row, $totals);

            $return .= '
            <tr>
                <td>'.$row['id_product'].'</td>
                <td>
                  <a href="'.Tools::safeOutput('index.php?tab=AdminProducts&updateproduct&id_product='.$row['id_product'].'&token='.$token_products).'">'.Tools::substr($row['name'], 0, 42).'</a>
                </td>
                <td class="center">'.$array_colors[$scores['active']].'</td>';

                $this->csvExport['product-'.$row['id_product']] = array(
                    $row['id_product'],
                    $row['name'],
                    $array_colors_info[$scores['active']]['text'],
                );

                foreach ($languages as $language) {
                    foreach ($array_conf as $conf) {
                        if (isset($conf['language'])) {
                            if (!empty($conf['table']['show'])) {
                                $this->csvExport['product-'.$row['id_product']][] = (!empty($conf['table']['countable']) ? (int)$row[$conf['table']['target'].'_'.$language['iso_code']] . ' - ' : '') . $array_colors_info[$scores[$conf['table']['target'].'_'.$language['iso_code']]]['text'];
                            }

                            $return .= '<td class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '"
                                    data-showtarget="'.$conf['table']['target'].'"
                                    data-filterevaluation="'.$conf['table']['target'].'-'.$scores[$conf['table']['target'].'_'.$language['iso_code']].'"
                                >' .
                                (!empty($conf['table']['countable']) ? (int)$row[$conf['table']['target'].'_'.$language['iso_code']] : '') .
                                ' ' . $array_colors[$scores[$conf['table']['target'].'_'.$language['iso_code']]] .
                            '</td>';
                        }
                    }
                }

                foreach ($array_conf as $conf) {
                    if (!isset($conf['language'])) {
                        if (!empty($conf['table']['show'])) {
                            $this->csvExport['product-'.$row['id_product']][] = (!empty($conf['table']['countable']) ? (int)$row[$conf['table']['target']] . ' - ' : '') . $array_colors_info[$scores[$conf['table']['target']]]['text'];
                        }

                        $return .= '<td class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '"
                                data-showtarget="'.$conf['table']['target'].'"
                                data-filterevaluation="'.$conf['table']['target'].'-'.$scores[$conf['table']['target']].'"
                            >' .
                            (!empty($conf['table']['countable']) ? (int)$row[$conf['table']['target']] : '') .
                            ' ' . $array_colors[$scores[$conf['table']['target']]] .
                        '</td>';
                    }
                }

                $return .= '<td class="center" >'.$array_colors[$scores['average']].'</td>';
            $return .= '</tr>';

            $this->csvExport['product-'.$row['id_product']][] = $array_colors_info[$scores['average']]['text'];
        }

        $return .= '</tbody>';

        $this->calcScoresTotals($totals);

//        Do not display average total for the moment
//        $return .= '
//			<tfoot>
//				<tr>
//					<th colspan="2"></th>
//					<th class="center"><span class="title_box active">'.$this->trans('Active', array(), 'Admin.Global').'</span></th>';
//                    $return .= $columnTitle;
//					$return .= '<th class="center"><span class="title_box active">'.$this->trans('Global', array(), 'Modules.Statscheckup.Admin').'</span></th>';
//                $return .= '</tr>
//				<tr>
//					<td colspan="2"></td>
//					<td class="center">'.$array_colors[$totals['active']].'</td>';
//
//                    foreach ($languages as $language) {
//                        foreach ($array_conf as $conf) {
//                            if (isset($conf['language'])) {
//                                $return .= '<td class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">' .
//                                    $array_colors[$totals[$conf['table']['target'].'_'.$language['iso_code']]] .
//                                '</td>';
//                            }
//                        }
//                    }
//
//                    foreach ($array_conf as $conf) {
//                        if (!isset($conf['language'])) {
//                            $return .= '<td class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">' .
//                                $array_colors[$totals['images']] .
//                            '</td>';
//                        }
//                    }
//
//					$return .= '<td class="center">'.$array_colors[$totals['average']].'</td>';
//                $return .= '</tr>
//			</tfoot>';

		$return .= '</table>
        </div>';

        if (Tools::isSubmit('export')) {
            $csv = new CSVCore(array(), 'export-statschekup-'.Tools::displayDate(date('Y-m-d')));
            $data = $this->getFormatedData($csv);
            $csv->output($data);
            $csv->headers();
            die;
        }

        return $return;
    }

    /**
     * Get formated data from array for csv extract
     *
     * @param CSVCore $csv
     * @return array
     */
    private function getFormatedData(CSVCore $csv)
    {
        $data = array();

        foreach ($this->csvExport as $line) {
            $data[] = $csv->output($line);
        }

        return $data;
    }

    /**
     * Calc scores for product
     *
     * @param $row
     * @param $totals
     * @return array
     */
    private function calcScoresProduct(&$row, &$totals)
    {
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        $employee = Context::getContext()->employee;
        $prop30 = ((strtotime($employee->stats_date_to.' 23:59:59') - strtotime($employee->stats_date_from.' 00:00:00')) / 60 / 60 / 24) / 30;
        $divisor = count($totals) - 1;

        $scores = array(
            'active' => ($row['active'] ? 2 : 0),
            'images' => ($row['images'] < Configuration::get('CHECKUP_IMAGES_LT') ? 0 : ($row['images'] >= Configuration::get('CHECKUP_IMAGES_GT') ? 2 : 1)),
            'sales' => (($row['sales'] * $prop30 < Configuration::get('CHECKUP_SALES_LT')) ? 0 : (($row['sales'] * $prop30 >= Configuration::get('CHECKUP_SALES_GT')) ? 2 : 1)),
            'stock' => (($row['stock'] < Configuration::get('CHECKUP_STOCK_LT')) ? 0 : (($row['stock'] >= Configuration::get('CHECKUP_STOCK_GT')) ? 2 : 1)),
            'features' => ($row['features'] < Configuration::get('CHECKUP_FEATURES_LT') ? 0 : ($row['features'] >= Configuration::get('CHECKUP_FEATURES_GT') ? 2 : 1)),
            'accessory' => ($row['accessory'] < Configuration::get('CHECKUP_ACCESSORY_LT') ? 0 : ($row['accessory'] >= Configuration::get('CHECKUP_ACCESSORY_GT') ? 2 : 1)),
            'carrier' => ($row['carrier'] < Configuration::get('CHECKUP_CARRIER_LT') ? 0 : ($row['carrier'] >= Configuration::get('CHECKUP_CARRIER_GT') ? 2 : 1)),
            'tags' => ($row['tags'] < Configuration::get('CHECKUP_TAGS_LT') ? 0 : ($row['tags'] >= Configuration::get('CHECKUP_TAGS_GT') ? 2 : 1)),

            'reference' => ((bool)$row['reference'] == (bool)Configuration::get('CHECKUP_REFERENCE') ? 2 : 0),
            'brand' => ((bool)$row['brand'] == (bool)Configuration::get('CHECKUP_BRAND') ? 2 : 0),
            'supplier' => ((bool)$row['supplier'] == (bool)Configuration::get('CHECKUP_SUPPLIER') ? 2 : 0),
            'category' => ((bool)$row['category'] == (bool)Configuration::get('CHECKUP_CATEGORY') ? 2 : 0),
            'isbn' => ((bool)$row['isbn'] == (bool)Configuration::get('CHECKUP_ISBN') ? 2 : 0),
            'ean13' => ((bool)$row['ean13'] == (bool)Configuration::get('CHECKUP_EAN13') ? 2 : 0),
            'upc' => ((bool)$row['upc'] == (bool)Configuration::get('CHECKUP_UPC') ? 2 : 0),
            'available_for_order' => ((bool)$row['available_for_order'] == (bool)Configuration::get('CHECKUP_AVAILABLE_ORDER') ? 2 : 0),
            'online_only' => ((bool)$row['online_only'] == (bool)Configuration::get('CHECKUP_ONLINE_ONLY') ? 2 : 0),

            'price' => ((bool)(int)$row['price'] == (bool)Configuration::get('CHECKUP_PRICE') ? 2 : 0),
            'wholesale_price' => ((bool)(int)$row['wholesale_price'] == (bool)Configuration::get('CHECKUP_WHOLESALE_PRICE') ? 2 : 0),
            'shipping' => ((bool)(int)$row['shipping'] == (bool)Configuration::get('CHECKUP_SHIPPING_COST') ? 2 : 0),
            'width' => ((bool)(int)$row['width'] == (bool)Configuration::get('CHECKUP_WIDTH') ? 2 : 0),
            'height' => ((bool)(int)$row['height'] == (bool)Configuration::get('CHECKUP_HEIGHT') ? 2 : 0),
            'depth' => ((bool)(int)$row['depth'] == (bool)Configuration::get('CHECKUP_DEPTH') ? 2 : 0),
            'weight' => ((bool)(int)$row['weight'] == (bool)Configuration::get('CHECKUP_WEIGHT') ? 2 : 0),
        );

        $descriptions = $db->executeS('
            SELECT l.iso_code, pl.description, pl.description_short, pl.meta_title, pl.meta_description
            FROM '._DB_PREFIX_.'product_lang pl
            LEFT JOIN '._DB_PREFIX_.'lang l
                ON pl.id_lang = l.id_lang
            WHERE l.active = 1 AND id_product = '.(int)$row['id_product'].Shop::addSqlRestrictionOnLang('pl'));

        $fields = array(
            'description' => 'CHECKUP_DESCRIPTIONS',
            'description_short' => 'CHECKUP_SHORT_DESCRIPTIONS',
            'meta_title' => 'CHECKUP_META_TITLE',
            'meta_description' => 'CHECKUP_META_DESCRIPTION',
        );

        foreach ($descriptions as $description) {
            if (!isset($description['iso_code'])) {
                continue;
            }

            foreach ($fields as $key => $field) {
                if (isset($description[$key])) {
                    $row[$key.'_'.$description['iso_code']] = Tools::strlen(strip_tags($description[$key]));
                }

                $scores[$key.'_'.$description['iso_code']] = ($row[$key.'_'.$description['iso_code']] < Configuration::get($field.'_LT')
                    ? 0 : ($row[$key.'_'.$description['iso_code']] > Configuration::get($field.'_GT')
                        ? 2 : 1));
            }
        }

        foreach ($scores as $key => $score) {
            $totals[$key] += (int)$scores[$key];
        }

        $scores['average'] = array_sum($scores) / $divisor;
        $scores['average'] = ($scores['average'] < 1 ? 0 : ($scores['average'] > 1.5 ? 2 : 1));

        return $scores;
    }

    /**
     * Calc scores totals
     *
     * @param $totals
     */
    private function calcScoresTotals(&$totals)
    {
        $divisor = count($totals) - 1;

        foreach ($totals as $key => $total) {
            if ('average' !== $key && 'products' !== $key) {
                $totals[$key] = $totals[$key] / $totals['products'];
                $totals[$key] = ($totals[$key] < 1 ? 0 : ($totals[$key] > 1.5 ? 2 : 1));
            }
        }

        $totals['average'] = array_sum($totals) / $divisor;
        $totals['average'] = ($totals['average'] < 1 ? 0 : ($totals['average'] > 1.5 ? 2 : 1));
    }
}
