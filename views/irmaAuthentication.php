<?php $this->layout('base', ['pageTitle' => $this->t('Sign In')]); ?>
<?php $this->start('content'); ?>
<!--
    irma.js obtained from https://gitlab.science.ru.nl/irma/github-mirrors/irma-frontend-packages/-/jobs/111202/artifacts/browse/irma-frontend/dist
    @see https://github.com/privacybydesign/irma-frontend-packages/tree/master/irma-frontend
-->
<script src="<?= $this->getAssetUrl($requestRoot, 'js/irma.js'); ?>"></script>
<script src="<?= $this->getAssetUrl($requestRoot, 'js/irma_impl.js'); ?>"></script>
<!--
    the _irma/verify endpoint is triggered after the attribute release with the
    IRMA app is complete. This is only used to inform the backend that the
    IRMA server needs to be queried to obtain the attribute...
-->
<div id="irmaAuth" data-session-ptr="<?= $this->e($sessionPtr); ?>">
    <form method="post" action="<?= $requestRoot; ?>_irma/verify">
    </form>
</div>
<?php $this->stop('content'); ?>
