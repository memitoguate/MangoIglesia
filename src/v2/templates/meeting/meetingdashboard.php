<?php
/*******************************************************************************
 *
 *  filename    : meetingdashboard.php
 *  last change : 2020-07-04
 *  website     : http://www.ecclesiacrm.com
 *  copyright   : 2020 Philippe Logel all right reserved not MIT licence
 *                This code can't be included in another software
 *
 ******************************************************************************/

use EcclesiaCRM\dto\SystemConfig;
use EcclesiaCRM\Utils\OutputUtils;
use EcclesiaCRM\dto\ChurchMetaData;

require $sRootDocument . '/Include/Header.php';
?>

<div class="card card-primary card-body">
    <div class="margin">
        <label><?= _("Room names") ?></label>
        <div class="btn-group">
            <a class="btn btn-app" id="newRoom"><i
                    class="fa fa-sticky-note"></i><?= _("Create Room") ?></a>
            <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown">
                <span class="caret"></span>
                <span class="sr-only">Menu déroulant</span>
            </button>
            <div class="dropdown-menu" role="menu">
                <a class="dropdown-item selectRoom" data-room="1"><?= _("Person") ?></a>
                <a class="dropdown-item selectRoom" data-room="2"><?= _("Family") ?></a>
                <a class="dropdown-item selectRoom" data-room="3"><?= _("Retired") ?></a>
                <a class="dropdown-item selectRoom" data-room="4"><?= _("Young") ?></a>
            </div>
            &nbsp;
            <a class="btn btn-app bg-orange" id="add-event"><i class="fa fa-calendar-plus-o"></i><?= _("Appointment") ?>
            </a>
        </div>
    </div>
</div>

<div class="row" style="height: 100%">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div
                    class="card-title"><?= _("Create Your Room Name below, Share it and Use it") ?></div>
            </div>
            <div class="card-body">
                <div id="meetingIframe" style="width:100%;height:600px">
                </div>
            </div>
        </div>
    </div>
</div>

<?php require $sRootDocument . '/Include/Footer.php'; ?>

<script src='<?= SystemConfig::getValue("sJitsiDomainScriptPath") ?>'></script>

<link href="<?= $sRootPath ?>/skin/external/bootstrap-colorpicker/bootstrap-colorpicker.min.css" rel="stylesheet">

<script src="<?= $sRootPath ?>/skin/external/bootstrap-datetimepicker/bootstrap-datetimepicker.min.js"></script>
<script src="<?= $sRootPath ?>/skin/external/bootstrap-colorpicker/bootstrap-colorpicker.min.js"
        type="text/javascript"></script>

<script src="<?= $sRootPath ?>/skin/external/ckeditor/ckeditor.js"></script>
<script src="<?= $sRootPath ?>/skin/js/ckeditor/ckeditorextension.js"></script>


<script nonce="<?= $sCSPNonce ?>">
    // jitsi code
    const domain = '<?= SystemConfig::getValue("sJitsiDomain") ?>';
    const options = {
        roomName: '<?= $roomName ?>',
        width: '100%',
        height: '100%',
        parentNode: document.querySelector('#meetingIframe')
    };
    const api = new JitsiMeetExternalAPI(domain, options);
    // end

    // page construction
    var sPageTitle = '<?= $sPageTitle ?>';

    window.CRM.churchloc = {
        lat: <?= OutputUtils::number_dot(ChurchMetaData::getChurchLatitude()) ?>,
        lng: <?= OutputUtils::number_dot(ChurchMetaData::getChurchLongitude()) ?>};
    window.CRM.mapZoom = <?= SystemConfig::getValue("iLittleMapZoom")?>;
</script>


<script src="<?= $sRootPath ?>/skin/js/calendar/EventEditor.js"></script>
<script src="<?= $sRootPath ?>/skin/js/meeting/meeting.js"></script>

<?php
if (SystemConfig::getValue('sMapProvider') == 'OpenStreetMap') {
    ?>
    <script src="<?= $sRootPath ?>/skin/js/calendar/OpenStreetMapEvent.js"></script>
<?php
} else if (SystemConfig::getValue('sMapProvider') == 'GoogleMaps') {
?>
    <!--Google Map Scripts -->
    <script
        src="https://maps.googleapis.com/maps/api/js?key=<?= SystemConfig::getValue('sGoogleMapKey') ?>"></script>

    <script src="<?= $sRootPath ?>/skin/js/calendar/GoogleMapEvent.js"></script>
<?php
} else if (SystemConfig::getValue('sMapProvider') == 'BingMaps') {
?>
    <script src="<?= $sRootPath ?>/skin/js/calendar/BingMapEvent.js"></script>
    <?php
}
?>

