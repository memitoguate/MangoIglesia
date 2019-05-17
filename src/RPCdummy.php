<?php
require 'Include/Config.php';
require 'Include/Functions.php';

use EcclesiaCRM\Utils\InputUtils;
use EcclesiaCRM\Utils\MiscUtils;
use EcclesiaCRM\Utils\RedirectUtils;
use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\SessionUser;


// Security
if ( !( SessionUser::getUser()->isFinanceEnabled() && SystemConfig::getBooleanValue('bEnabledFinance') ) ) {
    RedirectUtils::Redirect('Menu.php');
    exit;
}

$mode = $_GET['mode'];
$data = InputUtils::LegacyFilterInput($_GET['data'], 'int');
?>

<html>
  <head>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>" >
<?php
// Select the appropriate Javascript routine..
switch ($mode) {
  case CartCounter:
    ?>
          windowOnload = function ()
          {
            window.parent.updateCartCounter('<?= count($_SESSION['aPeopleCart']) ?>');
          }
    <?php
    break;

  case Envelope2Address:
    // Security check
    if (!SessionUser::getUser()->isFinanceEnabled()) {
        exit;
    }

    $sSQL = 'SELECT	per_Address1, per_Address2, per_City, per_State, per_Zip, per_Country,
                                                    fam_Address1, fam_Address2, fam_City, fam_State, fam_Zip, fam_Country
                                            FROM person_per	LEFT JOIN family_fam ON person_per.per_fam_ID = family_fam.fam_ID
                                            WHERE per_Envelope='.$data;
    $rsQuery = RunQuery($sSQL);

    if (mysqli_num_rows($rsQuery) == 0) {
        $sGeneratedHTML = 'invalid';
    } else {
        extract(mysqli_fetch_array($rsQuery));

        $sCity = SelectWhichInfo($per_City, $fam_City, false);
        $sState = SelectWhichInfo($per_State, $fam_State, false);
        $sZip = SelectWhichInfo($per_Zip, $fam_Zip, false);
        $sCountry = SelectWhichInfo($per_Country, $fam_Country, false);

        MiscUtils::SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, false);

        $sGeneratedHTML = '<b>'.gettext('Address Info:').'</b><br>';
        if ($sAddress1 != '') {
            $sGeneratedHTML .= $sAddress1.'<br>';
        }
        if ($sAddress2 != '') {
            $sGeneratedHTML .= $sAddress2.'<br>';
        }
        if ($sCity != '') {
            $sGeneratedHTML .= $sCity.', ';
        }
        if ($sState != '') {
            $sGeneratedHTML .= $sState;
        }
        if ($sZip != '') {
            $sGeneratedHTML .= ' '.$sZip;
        }
        if ($sCountry != '') {
            $sGeneratedHTML .= '<br>'.$sCountry;
        }
    }
    ?>
          windowOnload = function ()
          {
            window.parent.updateAddressInfo('<?= $sGeneratedHTML ?>');
          }
    <?php
    break;
}
?>
    </script>
  </head>
  <body onload="windowOnload();">
  </body>
</html>
