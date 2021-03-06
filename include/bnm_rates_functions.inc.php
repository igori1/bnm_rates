<?php
/**
 * @file
 * Functions which are not to be inside a .module.
 */

/**
 * Retrieves xmldata from bnm.org and stores into the DB.
 *
 * @param string $date
 *   The date of currency rates.
 * @param string $lang
 *   Language ('en', 'ru', 'ro', 'mo' or 'md'.
 *   ro=mo=md - this means the same language: Romanian, MOldavian or MolDavian.
 *
 * @return SimpleXMLElement $simple_xml
 *   The simple_xml object to foreach in.
 */
function bnm_rates_pull_xmldata($date = '', $lang = 'en') {
  if (empty($date)) {
    $date = date("d.m.Y");
  }
  $url = "http://www.bnm.md/{$lang}/official_exchange_rates?get_xml=1&date={$date}";

  $simple_xml = simplexml_load_file($url);
  if ($simple_xml) {
    $result = bnm_rates_store_data($simple_xml, $lang);
    watchdog('bnm_rates', "Store rates for {$date} lang={$lang}. Result: {$result}");
  }
  else {
    watchdog('bnm_rates', "SimpleXML NULL or FALSE for {$date} lang={$lang}.");
  }

  return $simple_xml;
}

/**
 * Saves xmldata into database.
 *
 * @param SimpleXMLElement $simple_xml
 *   The simple_xml object to foreach in.
 *
 * @see bnm_rates_pull_xmldata()
 */
function bnm_rates_store_data(SimpleXMLElement $simple_xml, $lang) {
  $attribs = $simple_xml->attributes();
  $valute_array = $simple_xml->children();
  foreach ($valute_array as $valute) {
    $attr = $valute->attributes();
    $valute_id = (string) $attr['ID'];
    db_merge('bnm_currency')
      ->key(array(
        'valute_id' => (int) $valute_id,
        'lang' => $lang,
      ))
      ->fields(
        array(
          'num_code' => (string) $valute->NumCode,
          'char_code' => (string) $valute->CharCode,
          'nominal' => (int) $valute->Nominal,
          'currency_name' => (string) $valute->Name,
          'in_block' => (string) '1',
        ))
      ->execute();

    db_merge('bnm_exchange_rate')
      ->key(array(
        'valute_id' => (int) $valute_id,
        'date' => (string) $attribs['Date'],
      ))
      ->fields(array(
        'value' => (float) $valute->Value,
      ))
      ->execute();
  }
}

/**
 * Select exchange rates from database.
 *
 * If there is no data in the database,
 * then call function bnm_rates_pull_xmldata($date = '', $lang = 'en')
 * if there is no result from bnm_rates-pull-xmldata then log this event into
 * watchdog.
 *
 * @param string $date
 *   The date of currency rates.
 *
 * @see bnm_rates_pull_xmldata()
 *
 * @return array $result
 *   Array of currency rates.
 */
function bnm_rates_get($date = '', $lang = 'en', $in_block = FALSE) {
  if (empty($date)) {
    $date = date("d.m.Y");
  }
  if (in_array($lang, array('ro', 'mo'))) {
    $lang = 'md';
  }
  if ($in_block) {
    $in_block_where = ' AND in_block = 1 ';
  }
  $query = db_query("SELECT valute_id, value, num_code, char_code, nominal,currency_name, lang, date
            FROM {bnm_exchange_rate} ber
            INNER JOIN {bnm_currency} bc using(valute_id)
            WHERE ber.date = :date " .
            $in_block_where .
            "AND bc.lang = :lang
            ORDER BY currency_name", array(':date' => $date, ':lang' => $lang));
  $result = $query->fetchAll();

  if (empty($result)) {
    bnm_rates_pull_xmldata($date, $lang);
    drupal_goto($GLOBALS['_GET']['q']);
  }

  return $result;
}
