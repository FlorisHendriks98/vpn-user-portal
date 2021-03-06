<?php $this->layout('base', ['activeItem' => 'account', 'pageTitle' => $this->t('Account')]); ?>
<?php $this->start('content'); ?>
    <h2><?=$this->t('Two-factor Enrollment'); ?></h2>

    <?php if ($requireTwoFactorEnrollment): ?>
        <p class="success">
            <?=$this->t('In order to continue, you must first enroll for Two-factor authentication!'); ?>
        </p>
    <?php endif; ?>

    <?php if (0 === count($twoFactorMethods)): ?>
        <p>
            <?=$this->t('Two-factor authentication (2FA) is disabled by the administrator.'); ?>
        </p>
    <?php else: ?>
        <?php if ($hasTotpSecret): ?>
            <p class="success">
                <?=$this->t('You are already enrolled for Two-factor authentication (2FA).'); ?>
            </p>
        <?php else: ?>
            <?php if (in_array('totp', $twoFactorMethods, true)): ?>
                <p>
                    <?=$this->t('Here you can enroll for two-factor authentication (2FA) using a Time-based One-time Password (TOTP).'); ?>
                    <?=$this->t('See the <a href="documentation#2fa">documentation</a> for more information on 2FA and a list of applications to use.'); ?>
                </p>

                <dl>
                    <dt><?=$this->t('Secret'); ?></dt>
                    <dd><code><?=$this->e($totpSecret); ?></code></dd>
                    <dt><?=$this->t('QR'); ?></dt>
                    <dd><img alt="<?=$this->t('QR'); ?>" src="qr/totp?secret=<?=$this->e($totpSecret); ?>"></dd>
                </dl>

                <p>
                    <?=$this->t('Scan the QR code using your 2FA application, or import the secret manually. Confirm the successful configuration by entering a 6 digit OTP generated by the application below.'); ?>
                </p>

                <?php if (isset($error_code)): ?>
                    <?php if ('invalid_otp_code' === $error_code): ?>
                        <p class="error"><?=$this->t('The OTP key you entered does not match the expected value for this OTP secret.'); ?></p>
                    <?php else: ?>
                        <p class="error"><?=$this->e($error_code); ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <form class="frm" method="post">
                    <fieldset>
                        <label for="otp_key"><?=$this->t('OTP'); ?></label>
                        <input type="text" id="totp_key" inputmode="numeric" placeholder="<?=$this->t('OTP'); ?>" name="totp_key" autocomplete="off" maxlength="6" required pattern="[0-9]{6}" autofocus>
                        <input type="hidden" name="totp_secret" value="<?=$this->e($totpSecret); ?>">
                    </fieldset>
                    <fieldset>
                        <button><?=$this->t('Verify'); ?></button>
                    </fieldset>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
<?php $this->stop('content'); ?>
