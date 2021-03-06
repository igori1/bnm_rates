<?php
/**
 * @file The bnm_rates.tpl.php.
 *
 * Template for typical(most viewed) exchange rates.
 */
$limit = $variables['limit'];
?>
<div id="bnm-rates-block-rates">
  <?php foreach($variables['rates'] as $curr_rate): ?>
    <div>
      <span class="currency-name"><?php print $curr_rate->currency_name; ?></span>
      <span class="currency_value"><?php print $curr_rate->value; ?></span>
    </div>
  <?php endforeach;?>
</div>
