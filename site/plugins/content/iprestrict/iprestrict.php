<?php
// No direct access
defined('_JEXEC') or die;

class PlgContentIprestrict extends JPlugin
{
    /**
     * Article-type contexts that will be checked
     * @var array
     */
    protected $allowedContexts = [
        'com_content.archive',
        'com_content.article',
        'com_content.category',
        'com_content.featured'
    ];

    /**
     * @var JForm
     */
    protected $_form = null;

    /**
     * Affects constructor behavior. If true, language files will be loaded automatically.
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Add 'restricted' checkbox to article edit form
     * @return bool
     */
    public function onContentPrepareForm($form, $data)
    {
        if (!($form instanceof JForm)) {
            return true;
        }

        if ('com_content.article' != $form->getName()) {
            return true;
        }

        $app = JFactory::getApplication();
        if (!$app->isAdmin()) {
            return true;
        }

        JForm::addFormPath(dirname(__FILE__) . '/form');
        $form->loadFile('iprestrict', false); // false = do not reset

        $this->_form = $form;

        return true;
    }

    /**
     * Chceck if the article is IP restricted, and redirect away from it (in case of a single article) or add a flag
     * @return bool
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        $app = JFactory::getApplication();

        if ($app->isAdmin()) {
            return;
        }

        // if there is no ip restriction present for the current user, we don't need to proceed
        // user state set in system plugin "iprestrict"
        if ($app->getUserState('iprestriction', 0) != 1) {
            return;
        }
        // check if article is in one of the contexts that should be checked
        if (!in_array($context, $this->allowedContexts)) {
            return;
        }

        // if the article has no attribs, it cannot have been set to restricted
        if (!isset($article->attribs)) {
            return;
        }

        $attribs = json_decode($article->attribs, true);

        // iprestrict has not been set for this article
        if (!isset($attribs['iprestrict']) || 1 != $attribs['iprestrict']) {
            return;
        }

        // we can now assume the ip restriction is in place for the article

        // if we are viewing a single article, redirect away from it
        if ($context == 'com_content.article' && $this->params->get('redirect')) {
            $app->redirect($this->params->get('redirect'));
        }

        // in all other contexts, we cannot unset the article or redirect, so we add a "restricted" flag
        // this needs to be implemented in the appropriate template overrides
        $article->iprestricted = 1;

        return true;
    }

    /**
     * Check if an IP address falls in one the defined ranges
     * @param $ip
     * @return bool
     */
    protected function checkRestrictedIpAllowed($ip)
    {
        if (empty($this->validator)) {
            $ipAddresses = trim($this->params->get('ipaddresses'));
            if (empty($ipAddresses)) {
                return true;
            }
            $ipAddresses = str_replace("\r\n", PHP_EOL, $ipAddresses);
            $ipAddresses = explode(PHP_EOL, $ipAddresses);

            $this->validator = new Picl_Validate_InIpRange(['allowedRanges' => $ipAddresses]);
        }

        return $this->validator->isValid($ip);
    }

    /**
     * Get the visitor's IP address
     * @return mixed
     */
    protected function getIpAddress()
    {
        if (
            isset($_SERVER['HTTP_CLIENT_IP']) &&
            filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)
        ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (
            isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
            filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)
        ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }
}
