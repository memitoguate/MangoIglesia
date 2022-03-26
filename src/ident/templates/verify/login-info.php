<?php
use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\Service\SystemService;
use EcclesiaCRM\dto\ChurchMetaData;

// Set the page title and include HTML header
$sPageTitle = "EcclesiaCRM - Family Verification";
require(SystemURLs::getDocumentRoot(). "/Include/HeaderNotLoggedIn.php");
?>
    <div class="login-box" id="Login" <?= ($_SESSION['iLoginType'] != "Lock") ? "" : 'style="display: none;"' ?>>
        <!-- /.login-logo -->
        <div class="card login-box-body card card-outline card-primary">
            <div class="card-header login-logo">
                Ecclesia<b>CRM</b><?= SystemService::getDBMainVersion() ?>
            </div>
            <div class="card-body login-card-body">

                <p class="login-box-msg">
                    <b><?= ChurchMetaData::getChurchName() ?></b><br/>
                    <?= _('Please Login') ?>
                </p>

                <?php
                if (isset($_GET['Timeout'])) {
                    $loginPageMsg = _('Your previous session timed out.  Please login again.');
                }

                // output warning and error messages
                if (isset($sErrorText)) {
                    echo '<div class="alert alert-error">' . $sErrorText . '</div>';
                }
                if (isset($loginPageMsg)) {
                    echo '<div class="alert alert-warning">' . $loginPageMsg . '</div>';
                }
                ?>

                <form class="form-signin" role="form" method="post" name="LoginForm" action="<?= SystemURLs::getRootPath() ?>/ident/my-profile/<?= $realToken ?>">
                    <div class="form-group has-feedback">
                        <input type="text" id="UserBox" name="User" class= "form-control form-control-sm" value="<?= $urlUserName ?>"
                               placeholder="<?= _('Email/Username') ?>" required>
                    </div>
                    <div class="form-group has-feedback">
                        <input type="password" id="PasswordBox" name="Password" class= "form-control form-control-sm" data-toggle="password"
                               placeholder="<?= _('Password') ?>" required value="<?= $urlPassword ?>">
                    </div>
                    <div class="row  mb-3">
                        <!-- /.col -->
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block"><i
                                    class="fas fa-sign-in-alt"></i> <?= _('Login') ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- /.login-box-body -->
    </div>

<?php
// Add the page footer
require(SystemURLs::getDocumentRoot(). "/Include/FooterNotLoggedIn.php");