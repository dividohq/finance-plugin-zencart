<?php
$outputStartBlock = '';
$outputEndBlock = '';
$output = '';

$outputStartBlock .= '<td><table class="noprint">'."\n";
$outputStartBlock .= '<tr style="background-color : #bbbbbb; border-style : dotted;">'."\n";
$outputEndBlock .= '</tr>'."\n";
$outputEndBlock .='</table></td>'."\n";

// prepare output based on suitable content components
if (defined('MODULE_PAYMENT_FINANCEPAYMENT_STATUS') && MODULE_PAYMENT_FINANCEPAYMENT_STATUS != '') {
  $output = '<!-- BOF: admin transaction processing tools -->';
  $output .= $outputStartBlock;
  $output .= $outputEndBlock;
  $output .= '<!-- EOF: admin transaction processing tools -->';
}
