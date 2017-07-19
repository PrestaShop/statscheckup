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
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        $confs = $this->getConfigurationModule();
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
            'CHECKUP_IMAGES_LT' => 1,
            'CHECKUP_IMAGES_GT' => 2,
            'CHECKUP_SALES_LT' => 1,
            'CHECKUP_SALES_GT' => 2,
            'CHECKUP_STOCK_LT' => 1,
            'CHECKUP_STOCK_GT' => 1,
            'CHECKUP_REFERENCE' => true,
            'CHECKUP_PRICE' => true,
            'CHECKUP_WHOLESALE_PRICE' => true,
            'CHECKUP_WIDTH' => true,
            'CHECKUP_HEIGHT' => true,
            'CHECKUP_DEPTH' => true,
            'CHECKUP_WEIGHT' => true,
            'CHECKUP_ISBN' => true,
            'CHECKUP_EAN13' => true,
            'CHECKUP_UPC' => true,
        );
    }

    public function hookActionAdminControllerSetMedia()
    {
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
        $this->html .= $this->showOrderForm();
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
                Configuration::updateValue($confname, (int)Tools::getValue($confname));
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

        $languages = Language::getLanguages();

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
        return array(
            'DESCRIPTIONS' => array(
                'name' => $this->trans('Descriptions', array(), 'Modules.Statscheckup.Admin'),
                'text' => $this->trans('chars (without HTML)', array(), 'Modules.Statscheckup.Admin'),
                'language' => true,
                'table' => array(
                    'target' => 'description',
                    'title' => $this->trans('Desc.', array(), 'Modules.Statscheckup.Admin'),
                    'countable' => true,
                    'show' => true,
                )
            ),
            'SHORT_DESCRIPTIONS' => array(
                'name' => $this->trans('Short descriptions', array(), 'Modules.Statscheckup.Admin'),
                'text' => $this->trans('chars (without HTML)', array(), 'Modules.Statscheckup.Admin'),
                'language' => true,
                'table' => array(
                    'target' => 'description_short',
                    'title' => $this->trans('Short desc.', array(), 'Modules.Statscheckup.Admin'),
                    'countable' => true,
                    'show' => true,
                )
            ),
            'IMAGES' => array(
                'name' => $this->trans('Images', array(), 'Admin.Global'),
                'text' => $this->trans('images', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'images',
                    'title' => $this->trans('Images', array(), 'Admin.Global'),
                    'countable' => true,
                    'show' => true,
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
                    'show' => true,
                )
            ),
            'STOCK' => array(
                'name' => $this->trans('Available quantity for sale', array(), 'Admin.Global'),
                'text' => strtolower($this->trans('Quantity', array(), 'Admin.Global')),
                'table' => array(
                    'target' => 'stock',
                    'title' => $this->trans('Available quantity for sale', array(), 'Admin.Global'),
                    'countable' => true,
                    'show' => true,
                )
            ),
            'REFERENCE' => array(
                'type' => 'switch',
                'name' => $this->trans('Reference', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'reference',
                    'title' => $this->trans('Reference', array(), 'Admin.Global'),
                    'show' => true,
                )
            ),
            'PRICE' => array(
                'type' => 'switch',
                'name' => $this->trans('Price', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'price',
                    'title' => $this->trans('Price', array(), 'Admin.Global'),
                    'show' => true,
                )
            ),
            'WHOLESALE_PRICE' => array(
                'type' => 'switch',
                'name' => $this->trans('Wholesale price', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'wholesale_price',
                    'title' => $this->trans('Wholesale price', array(), 'Admin.Global'),
                    'show' => true,
                )
            ),
            'WIDTH' => array(
                'type' => 'switch',
                'name' => $this->trans('Width', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'width',
                    'title' => $this->trans('Width', array(), 'Admin.Global'),
                )
            ),
            'HEIGHT' => array(
                'type' => 'switch',
                'name' => $this->trans('Height', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'height',
                    'title' => $this->trans('Height', array(), 'Admin.Global'),
                )
            ),
            'DEPTH' => array(
                'type' => 'switch',
                'name' => $this->trans('Depth', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'depth',
                    'title' => $this->trans('Depth', array(), 'Admin.Global'),
                )
            ),
            'WEIGHT' => array(
                'type' => 'switch',
                'name' => $this->trans('Weight', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'weight',
                    'title' => $this->trans('Weight', array(), 'Admin.Global'),
                )
            ),
            'ISBN' => array(
                'type' => 'switch',
                'name' => $this->trans('ISBN', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'isbn',
                    'title' => $this->trans('ISBN', array(), 'Admin.Global'),
                )
            ),
            'EAN13' => array(
                'type' => 'switch',
                'name' => $this->trans('EAN 13', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'ean13',
                    'title' => $this->trans('EAN 13', array(), 'Admin.Global'),
                )
            ),
            'UPC' => array(
                'type' => 'switch',
                'name' => $this->trans('UPC', array(), 'Admin.Global'),
                'table' => array(
                    'target' => 'upc',
                    'title' => $this->trans('UPC', array(), 'Admin.Global'),
                )
            ),
        );
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
          (
                SELECT COUNT(*)
                FROM '._DB_PREFIX_.'image i
                '.Shop::addSqlAssociation('image', 'i').'
                WHERE i.id_product = p.id_product
            ) as images, (
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
        $return = '<form action="'.Tools::safeOutput(AdminController::$currentIndex.'&token='.Tools::getValue('token').'&module='.$this->name).'" method="post" class="checkup form-horizontal">
        <table class="table checkup">
            <thead>
                <tr>
                    <th>'.$this->trans('Visibility', array(), 'Modules.Statscheckup.Admin').'</th>
                    <th></th>
                    <th><span class="title_box active">'.$array_colors[0].' '.$this->trans('Not enough', array(), 'Modules.Statscheckup.Admin').'</span></th>
                    <th><span class="title_box active">'.$array_colors[2].' '.$this->trans('Alright', array(), 'Modules.Statscheckup.Admin').'</span></th>
                </tr>
            </thead>';

        foreach ($array_conf as $conf => $translations) {
            $return .= '
				<tbody>
					<tr>
					    <td>
					        <i class="' . (empty($translations['table']['show']) ? 'icon-eye-slash' : 'icon-eye') . ' toggle-show pointer" data-target="'.strtolower($conf).'" aria-hidden="true"> </i>
						</td>
						<td>
							<label class="control-label col-lg-12">'.$translations['name'].'</label>
						</td>
						<td>';

            if (!isset($translations['type']) || $translations['type'] !== 'switch') {
                $return .= '<div class="row">
                                    <div class="col-lg-11 input-group">
                                        <span class="input-group-addon">' . $this->trans('Less than', array(), 'Modules.Statscheckup.Admin') . '</span>
                                        <input type="text" name="CHECKUP_' . $conf . '_LT" value="' . Tools::safeOutput(Tools::getValue('CHECKUP_' . $conf . '_LT', Configuration::get('CHECKUP_' . $conf . '_LT'))) . '" />
                                        <span class="input-group-addon">' . $translations['text'] . '</span>
                                    </div>
                                </div>';
            }

            $return .= '</td>
                        <td>';

            if (isset($translations['type']) && $translations['type'] === 'switch') {
                $return .= '<span class="switch prestashop-switch fixed-width-lg">
                                    <input type="radio" name="CHECKUP_' . $conf . '" '.
                    (Tools::getValue('CHECKUP_' . $conf, Configuration::get('CHECKUP_' . $conf)) === '1' ? 'checked': '').
                    ' value="1" id="CHECKUP_' . $conf . '_on" >
                                    <label for="CHECKUP_' . $conf . '_on" class="radioCheck">'.$this->trans('Yes', array(), 'Admin.Global').'</label>
                
                                    <input type="radio" name="CHECKUP_' . $conf . '" '.
                    (Tools::getValue('CHECKUP_' . $conf, Configuration::get('CHECKUP_' . $conf)) === '0' ? 'checked': '').
                    ' value="0" id="CHECKUP_' . $conf . '_off" >
                                    <label for="CHECKUP_' . $conf . '_off" class="radioCheck">'.$this->trans('No', array(), 'Admin.Global').'</label>
                                    <a class="slide-button btn"></a>
                              </span>';
            } else {
                $return .= '<div class="row">
                                    <div class="col-lg-12 input-group">
                                        <span class="input-group-addon">' . $this->trans('Greater than', array(), 'Modules.Statscheckup.Admin') . '</span>
                                        <input type="text" name="CHECKUP_' . $conf . '_GT" value="' . Tools::safeOutput(Tools::getValue('CHECKUP_' . $conf . '_GT', Configuration::get('CHECKUP_' . $conf . '_GT'))) . '" />
                                        <span class="input-group-addon">' . $translations['text'] . '</span>
                                     </div>
                                 </div>';
            }

            $return .= '</td>
                    </tr>
                </tbody>';
        }

        $return .= '</table>
			<button type="submit" name="submitCheckup" class="btn btn-default pull-right">
				<i class="icon-save"></i> '.$this->trans('Save', array(), 'Admin.Actions').'
            </button>
		</form>';

        return $return;
    }

    /**
     * Show the select to change the order form
     *
     * @return string
     */
    private function showOrderForm()
    {
        return '<form action="'.Tools::safeOutput(AdminController::$currentIndex.'&token='.Tools::getValue('token').'&module='.$this->name).'" method="post" class="form-horizontal alert">
			<div class="row">
				<div class="col-lg-12">
					<label class="control-label pull-left">'.$this->trans('Order by', array(), 'Modules.Statscheckup.Admin').'</label>
					<div class="col-lg-3">
						<select name="submitCheckupOrder" onchange="this.form.submit();">
							<option value="1">'.$this->trans('ID', array(), 'Admin.Global').'</option>
							<option value="2" '.($this->context->cookie->checkup_order == 2 ? 'selected="selected"' : '').'>'.$this->trans('Name', array(), 'Admin.Global').'</option>
							<option value="3" '.($this->context->cookie->checkup_order == 3 ? 'selected="selected"' : '').'>'.$this->trans('Sales', array(), 'Admin.Global').'</option>
						</select>
					</div>
				</div>
			</div>
		</form>';
    }

    /**
     * Construct column title for the table
     *
     * @param $array_conf
     * @return string
     */
    private function showColumnTitle($array_conf)
    {
        $languages = Language::getLanguages();

        $columnTitle = '';

        foreach ($languages as $language) {
            foreach ($array_conf as $conf) {
                if (isset($conf['language'])) {
                    $columnTitle .= '<th class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">
                        <span class="title_box active">'.$conf['table']['title'].' ('.Tools::strtoupper($language['iso_code']).')</span>
                    </th>';
                }
            }
        }

        foreach ($array_conf as $conf) {
            if (!isset($conf['language'])) {
                $columnTitle .= '<th class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">
                    <span class="title_box active">'.$conf['table']['title'].'</span>
                </th>';
            }
        }

        return $columnTitle;
    }

    private function showResult($result, $array_conf)
    {
        $languages = Language::getLanguages();

        $token_products = Tools::getAdminToken('AdminProducts'.(int)Tab::getIdFromClassName('AdminProducts').(int)Context::getContext()->employee->id);

        $array_colors = $this->getArrayColor();

        $totals = $this->initTotalsEvaluation($array_conf);

        $columnTitle = $this->showColumnTitle($array_conf);

        $return = '<div style="overflow-x:auto">
		<table class="table checkup2">
			<thead>
				<tr>
					<th><span class="title_box active">'.$this->trans('ID', array(), 'Admin.Global').'</span></th>
					<th><span class="title_box active">'.$this->trans('Item', array(), 'Admin.Global').'</span></th>
					<th class="center"><span class="title_box active">'.$this->trans('Active', array(), 'Admin.Global').'</span></th>';
                    $return .= $columnTitle;
//                    $return .= '<th class="center"><span class="title_box active">'.$this->trans('Global', array(), 'Modules.Statscheckup.Admin').'</span></th>';
                $return .= '</tr>
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

                foreach ($languages as $language) {
                    foreach ($array_conf as $conf) {
                        if (isset($conf['language'])) {
                            $return .= '<td class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">' .
                                (!empty($conf['table']['countable']) ? (int)$row[$conf['table']['target'].'_'.$language['iso_code']] : '') .
                                ' ' . $array_colors[$scores[$conf['table']['target'].'_'.$language['iso_code']]] .
                            '</td>';
                        }
                    }
                }

                foreach ($array_conf as $conf) {
                    if (!isset($conf['language'])) {
                        $return .= '<td class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">' .
                            (!empty($conf['table']['countable']) ? (int)$row[$conf['table']['target']] : '') .
                            ' ' . $array_colors[$scores[$conf['table']['target']]] .
                        '</td>';
                    }
                }

//                $return .= '<td class="center" >'.$array_colors[$scores['average']].'</td>';
            $return .= '</tr>';
        }

        $return .= '</tbody>';

        $this->calcScoresTotals($totals);

        $return .= '
			<tfoot>
				<tr>
					<th colspan="2"></th>
					<th class="center"><span class="title_box active">'.$this->trans('Active', array(), 'Admin.Global').'</span></th>';
                    $return .= $columnTitle;
//					$return .= '<th class="center"><span class="title_box active">'.$this->trans('Global', array(), 'Modules.Statscheckup.Admin').'</span></th>';
                $return .= '</tr>
				<tr>
					<td colspan="2"></td>
					<td class="center">'.$array_colors[$totals['active']].'</td>';

                    foreach ($languages as $language) {
                        foreach ($array_conf as $conf) {
                            if (isset($conf['language'])) {
                                $return .= '<td class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">' .
                                    $array_colors[$totals[$conf['table']['target'].'_'.$language['iso_code']]] .
                                '</td>';
                            }
                        }
                    }

                    foreach ($array_conf as $conf) {
                        if (!isset($conf['language'])) {
                            $return .= '<td class="center ' . (empty($conf['table']['show']) ? 'hidden' : '') . '" data-showtarget="'.$conf['table']['target'].'">' .
                                $array_colors[$totals['images']] .
                            '</td>';
                        }
                    }

//					$return .= '<td class="center">'.$array_colors[$totals['average']].'</td>';
                $return .= '</tr>
			</tfoot>
		</table></div>';

        return $return;
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
            'images' => ($row['images'] < Configuration::get('CHECKUP_IMAGES_LT') ? 0 : ($row['images'] > Configuration::get('CHECKUP_IMAGES_GT') ? 2 : 1)),
            'sales' => (($row['sales'] * $prop30 < Configuration::get('CHECKUP_SALES_LT')) ? 0 : (($row['sales'] * $prop30 > Configuration::get('CHECKUP_SALES_GT')) ? 2 : 1)),
            'stock' => (($row['stock'] < Configuration::get('CHECKUP_STOCK_LT')) ? 0 : (($row['stock'] > Configuration::get('CHECKUP_STOCK_GT')) ? 2 : 1)),
            'reference' => ((bool)$row['reference'] == (bool)Configuration::get('CHECKUP_REFERENCE') ? 2 : 0),
            'price' => ((bool)(int)$row['price'] == (bool)Configuration::get('CHECKUP_PRICE') ? 2 : 0),
            'wholesale_price' => ((bool)(int)$row['wholesale_price'] == (bool)Configuration::get('CHECKUP_WHOLESALE_PRICE') ? 2 : 0),
            'width' => ((bool)(int)$row['width'] == (bool)Configuration::get('CHECKUP_WIDTH') ? 2 : 0),
            'height' => ((bool)(int)$row['height'] == (bool)Configuration::get('CHECKUP_HEIGHT') ? 2 : 0),
            'depth' => ((bool)(int)$row['depth'] == (bool)Configuration::get('CHECKUP_DEPTH') ? 2 : 0),
            'weight' => ((bool)(int)$row['weight'] == (bool)Configuration::get('CHECKUP_WEIGHT') ? 2 : 0),
            'isbn' => ((bool)$row['isbn'] == (bool)Configuration::get('CHECKUP_ISBN') ? 2 : 0),
            'ean13' => ((bool)$row['ean13'] == (bool)Configuration::get('CHECKUP_EAN13') ? 2 : 0),
            'upc' => ((bool)$row['upc'] == (bool)Configuration::get('CHECKUP_UPC') ? 2 : 0),
        );

        $descriptions = $db->executeS('
            SELECT l.iso_code, pl.description, pl.description_short
            FROM '._DB_PREFIX_.'product_lang pl
            LEFT JOIN '._DB_PREFIX_.'lang l
                ON pl.id_lang = l.id_lang
            WHERE id_product = '.(int)$row['id_product'].Shop::addSqlRestrictionOnLang('pl'));

        foreach ($descriptions as $description) {
            if (isset($description['iso_code']) && isset($description['description'])) {
                $row['description_'.$description['iso_code']] = Tools::strlen(strip_tags($description['description']));
            }
            if (isset($description['iso_code']) && isset($description['description_short'])) {
                $row['description_short_'.$description['iso_code']] = Tools::strlen(strip_tags($description['description_short']));
            }

            if (isset($description['iso_code'])) {
                $scores['description_'.$description['iso_code']] = ($row['description_'.$description['iso_code']] < Configuration::get('CHECKUP_DESCRIPTIONS_LT')
                    ? 0 : ($row['description_'.$description['iso_code']] > Configuration::get('CHECKUP_DESCRIPTIONS_GT')
                        ? 2 : 1));

                $scores['description_short_'.$description['iso_code']] = ($row['description_short_'.$description['iso_code']] < Configuration::get('CHECKUP_SHORT_DESCRIPTIONS_LT')
                    ? 0 : ($row['description_short_'.$description['iso_code']] > Configuration::get('CHECKUP_SHORT_DESCRIPTIONS_GT')
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
