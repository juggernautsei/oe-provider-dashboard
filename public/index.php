<?php

/**
 * Provider Dashboard — missing documentation and coding.
 *
 * Extracted from Ace702 interface/provider_dashboard into module structure.
 *
 * @package OpenEMR
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

require_once __DIR__ . '/_init.php';

use Juggernaut\ProviderDashboard\Module\Controllers\DashboardData;
use Juggernaut\ProviderDashboard\Module\Controllers\SupervisorFeedback;
use OpenEMR\Billing\BillingUtilities;
use OpenEMR\Common\Csrf\CsrfUtils;

$ctx = provider_dashboard_context();
$assetBase = rtrim($ctx['moduleWebPath'], '/');
$data = new DashboardData($ctx['authUserID']);
$feedback = new SupervisorFeedback();
$userId = $ctx['authUserID'];
$csrfToken = provider_dashboard_csrf_token();
$title = xlt('Tasks');
?>
<!doctype html>
<html>

<?php require_once dirname(__DIR__) . '/resources/header_common.php'; ?>
<meta name="csrf-token" content="<?php echo attr($csrfToken); ?>">
<input type="hidden" id="csrf_token_form" value="<?php echo attr($csrfToken); ?>">
<style>
    .hoverFeedback {
        position: relative;
        display: inline-block;
    }

    .hoverFeedback .tooltip {
        visibility: hidden;
        width: auto;
        background-color: #333;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        margin-left: -75px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .hoverFeedback:hover .tooltip {
        visibility: visible;
        opacity: 1;
    }

</style>
  <body>
  <div class="wrapper d-flex align-items-stretch">
			<nav id="sidebar">
				<div class="custom-menu">
                  <button type="button" id="sidebarCollapse" class="btn btn-primary">
                      <i class="fa fa-bars"></i>
                      <span class="sr-only">Toggle Menu</span>
                    </button>
                </div>

            <?php require_once dirname(__DIR__) . '/resources/menu_common.php'; ?>

	        <div class="footer">
               <p>by Juggernaut Systems Express</p>
	        </div>

	      </div>
    	</nav>

        <!-- Page Content  -->
      <div id="content" class="p-4 p-md-5 pt-5">
          <h2><?php echo xlt('Tasks') ?></h2>
            <p><?php echo xlt('This report shows all encounters that have not been documented or coded.') ?></p>
        <?php
        $form_orderby = '';
            $staff = $data->checkIfSupervisor();

            $userId = $userId;
            
            if (!empty($staff)) {
                $res = $data->encounterMultiProviderData($staff);
            } else {
                $res = $data->encounterSingleProviderData();
            }
        ?>
          <div class="d-flex justify-content-end mb-2">
              <button data-toggle="modal" data-target="#mySignModal" class="esign-button-form btn btn-text btn-sm" style="background: #866ec7"><i class="fa fa-signature"></i>&nbsp;<?php echo xlt('eSign All'); ?></button>
          </div>
          <table class='table' id='mymaintable'>
              <thead class='thead-light'>
                  <th>
                      <a href="nojs.php" onclick="return dosort('doctor')"
                          <?php echo ($form_orderby == "doctor") ? " style=\"color: var(--success)\"" : ""; ?>><?php echo xlt('Provider'); ?> </a>
                  </th>
                  <th>
                      <a href="nojs.php" onclick="return dosort('time')"
                          <?php echo ($form_orderby == "time") ? " style=\"color: var(--success)\"" : ""; ?>><?php echo xlt('Date'); ?></a>
                  </th>
                  <th>
                      <a href="nojs.php" onclick="return dosort('patient')"
                          <?php echo ($form_orderby == "patient") ? " style=\"color: var(--success)\"" : ""; ?>><?php echo xlt('Patient'); ?></a>
                  </th>
                  <th>
                      <a href="nojs.php" onclick="return dosort('pubpid')"
                          <?php echo ($form_orderby == "pubpid") ? " style=\"color: var(--success)\"" : ""; ?>><?php echo xlt('ID'); ?></a>
                  </th>
                  <th>
                      <?php echo xlt('Status'); ?>
                  </th>
                  <th>
                      <?php echo xlt('Encounter'); ?>
                  </th>
                  <th>
                      <a href="nojs.php" onclick="return dosort('encounter')"
                          <?php echo ($form_orderby == "encounter") ? " style=\"color: var(--success)\"" : ""; ?>><?php echo xlt('Encounter Number'); ?></a>
                  </th>
                  <th>
                      <?php echo xlt('Form'); ?>
                  </th>
                  <th>
                      <?php echo xlt('Coding'); ?>
                  </th>
                  <th>
                      <?php echo xlt('Signed'); ?>
                  </th>
              </thead>
              <tbody>
              <?php
              if ($res) {

            $lastdocname = "";
            $doc_encounters = 0;
            while ($row = sqlFetchArray($res)) {
                $signatures = $data->getDocumentSigner($row['encounter']);

                if ($signatures['count'] > 1) {
                    continue;
                }
                $patient_id = $row['pid'];

                      $docname = '';
                      if (!empty($row['ulname']) || !empty($row['ufname'])) {
                          $docname = $row['ulname'];
                          if (!empty($row['ufname']) || !empty($row['umname'])) {
                              $docname .= ', ' . $row['ufname'] . ' ' . $row['umname'];
                          }
                      }
                      $errmsg  = "";

                          // Fetch all other forms for this encounter.
                          $encnames = '';
                          $encarr = getFormByEncounter(
                              $patient_id,
                              $row['encounter'],
                              "formdir, user, form_name, form_id"
                          );
                          //var_dump($encarr);
                          if ($encarr != '') {
                              foreach ($encarr as $enc) {
                                  if ($enc['formdir'] == 'newpatient') {
                                      continue;
                                  }

                                  if ($encnames) {
                                      $encnames .= '<br />';
                                  }

                                  $encnames .= text($enc['form_name']); // need to html escape it here for output below
                              }
                          }
                          if ($encnames !== '') {
                              //continue;
                          }
                          // Fetch coding and compute billing status.
                          $coded = "";
                          $billed_count = 0;
                          $unbilled_count = 0;
                          if (
                              $billres = BillingUtilities::getBillingByEncounter(
                                  $row['pid'],
                                  $row['encounter'],
                                  "code_type, code, code_text, billed"
                              )
                          ) {
                              foreach ($billres as $billrow) {
                                  // $title = addslashes($billrow['code_text']);
                                  if ($billrow['code_type'] != 'COPAY' && $billrow['code_type'] != 'TAX') {
                                      $coded .= $billrow['code'] . ', ';
                                      if ($billrow['billed']) {
                                          ++$billed_count;
                                      } else {
                                          ++$unbilled_count;
                                      }
                                  }
                              }

                              $coded = substr($coded, 0, strlen($coded) - 2);
                          }

                          // Figure product sales into billing status.
                          $sres = sqlStatement("SELECT billed FROM drug_sales " .
                              "WHERE pid = ? AND encounter = ?", array($row['pid'], $row['encounter']));
                          while ($srow = sqlFetchArray($sres)) {
                              if ($srow['billed']) {
                                  ++$billed_count;
                              } else {
                                  ++$unbilled_count;
                              }
                          }

                          // Compute billing status.
                          /*if ($billed_count && $unbilled_count) {
                              $status = xl('Mixed');
                          } elseif ($billed_count) {
                              $status = xl('Closed');
                          } elseif ($unbilled_count) {
                              $status = xl('Open');
                          } else {
                              $status = xl('Empty');
                          }*/
                            $feedData = '';
                            if(!empty($encnames)){
                                $feedData = $feedback->getSupervisorFeedback($encnames,$row['pid'], $row['encounter']);
                            }
                            if($row['review_status'] == '1' && !empty($feedData)){
                                $status = 'InProgress';
                            } else if ($row['review_status'] == '1') {
                                $status = 'Ready';
                            } else {
                                $status = 'Not Ready';
                            }
                          ?>
                          <tr bgcolor='<?php echo attr($bgcolor ?? ''); ?>'>
                              <td>
                                  <?php echo ($docname == $lastdocname) ? "" : text($docname) ?>&nbsp;
                              </td>
                              <td>
                                  <?php echo text(oeFormatShortDate(substr($row['date'], 0, 10))) ?>&nbsp;
                              </td>
                              <td>
                                  <?php echo text($row['lname'] . ', ' . $row['fname'] . ' ' . $row['mname']); ?>&nbsp;
                              </td>
                              <td>
                                  <?php echo text($row['pubpid']); ?>&nbsp;
                              </td>
                              <td>
                                  <div id="documentstatus" class="documentstatus <?php echo ($status == 'InProgress') ? 'hoverFeedback' : '' ?>" data-status="<?php echo ($row['review_status'] !== null) ? $row['review_status'] : 0 ?>"
                                       data-id="<?php echo text($row['id']); ?>"
                                       data-encounter="<?php echo text($row['encounter']); ?>"
                                      >
                                  <span class="tooltip"><?php echo ($status == 'InProgress') ? $feedData : ''; ?></span>
                                  <?php echo text($status); ?>
                                  </div>
                              </td>
                              <td>
                                  <?php echo text($row['reason']); ?>&nbsp;
                              </td>
                              <td>
                                  <?php echo "<input type='button' class='btn btn-sm btn-secondary' value='" .
                                      attr($row['encounter']) . "-" . attr($row['pid']) .
                                      "' onClick='toEncounter(" . attr_js($row['pid']) . ", " . attr_js($row['encounter']) .
                                      "); ' />" ?> &nbsp;
                              </td>
                              <td>
                                  <?php echo $encnames; //since this variable contains html, have already html escaped it above ?>&nbsp;
                              </td>
                              <td>
                                  <?php echo text($coded); ?>
                              </td>
                              <td>
                                  <?php echo text($signatures['signers']); ?>
                              </td>
                          </tr>
                          <?php

                      $lastdocname = $docname;

            }
        } else {
            echo "<tr><td colspan='9'>" . xlt('Congratulation you have no undocumented visits') . "</td></tr>";
        }
        ?>
        </tbody>
    </table>
</div>
</div>
<div id="mySignModal" class="modal fade" role="dialog">
    <div class="modal-dialog">

        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-body">
                <form id="sign_all_modal">

                    <div class="text-center">
                        <span><?php echo xlt("Your password is your signature"); ?></span>
                    </div>

                    <div class="">
                        <label for='password'><?php echo xlt('Password'); ?></label>
                        <input type='password' class="form-control" id='password' name='password' size='10' placeholder="<?php echo xla("Enter your password to sign the form"); ?>" />
                    </div>

                    <div class="esign-signature-form-element form-group">
                        <label for='amendment'><?php echo xlt("Amendment"); ?></label>
                        <textarea class="form-control" name='amendment' id='amendment' placeholder='<?php echo xla("Enter an amendment..."); ?>'></textarea>
                    </div>
                    <input type="hidden" id="user_id" name="user_id" value="<?php echo $userId ?>">

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <input type='submit' class="btn btn-primary btn-sm" value='<?php echo xla('Sign'); ?>' />
                    </div>
                </form>

                <span class="text-center text-danger" id="errorMessage"></span>

            </div>
        </div>

    </div>
</div>
<?php
    function show_doc_total($lastdocname, $doc_encounters)
    {
        if ($lastdocname) {
            echo " <tr>\n";
            echo "  <td class='detail'>" .  text($lastdocname) . "</td>\n";
            echo "  <td class='detail' align='right'>" . text($doc_encounters) . "</td>\n";
            echo " </tr>\n";
        }
    }
?>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <?php require_once dirname(__DIR__) . '/resources/footer_common.php'?>
  <script src="<?php echo attr($assetBase . '/js/statuschange.js'); ?>"></script>
  <script src="<?php echo attr($assetBase . '/js/signAll.js'); ?>"></script>
    <script>
        // Called to switch to the specified encounter having the specified DOS.
        function toEncounter(newpid, enc) {
            top.restoreSession();
            top.RTop.location = "<?php echo attr($ctx['webroot']); ?>/interface/patient_file/summary/demographics.php?set_pid=" + encodeURIComponent(newpid) + "&set_encounterid=" + encodeURIComponent(enc);
        }
    </script>

</body>
</html>
