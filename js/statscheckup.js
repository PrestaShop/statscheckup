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

$(document).ready(function() {

  $('a.checkup-configuration').on('click', function() {
    if ("true" === $(this).data('open')) {
      $('table.checkup > tbody > tr').removeClass('hidden');
      $('table.checkup > tbody > tr.grised-td').addClass('hidden');

      $(this).html($(this).data('expand'));
      $(this).data('open', 'false');
    } else {
      $('table.checkup > tbody > tr').removeClass('hidden');

      $(this).html($(this).data('collapse'));
      $(this).data('open', 'true');
    }
  });

  $('input.toggle-show').on('click', function() {
    let target = $(this).data('target');
    let tr = $(this).closest('tr');

    tr.toggleClass('grised-td');
    tr.find('input:not(.toggle-show)').attr('disabled', !$(this).is(':checked'));

    $('[data-showtarget="' + target + '"]').toggleClass('hidden');
  });

  $('select.change-filter').on('change', function() {
    let evaluation = $('select.change-filter[name="change-filter-evaluation"]').val();
    let type = $('select.change-filter[name="change-filter-type"]').val();
    let typeEvaluation = type + '-' + evaluation;

    if ('-1' === evaluation) {
      $('table.checkup2 td[data-filterevaluation]').closest('tr').show();
    } else {
      $('table.checkup2 td[data-filterevaluation!="' + typeEvaluation + '"]').closest('tr').hide();
      $('table.checkup2 td[data-filterevaluation="' + typeEvaluation + '"]').closest('tr').show();
    }
  });

});
