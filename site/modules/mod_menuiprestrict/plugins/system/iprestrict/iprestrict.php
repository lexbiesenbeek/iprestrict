<?php
// No direct access
defined('_JEXEC') or die;

/**
 * Class PlgSystemIprestrict
 * Check if restriction is applicable for the current IP and set a user state param to notify other extensions about it
 */
class PlgSystemIprestrict extends JPlugin
{
    public function onAfterInitialise()
    {
        $this->setIpRestrictionSession();
    }

    /**
     * Set a user state param to indicate to other extensions that IP restriction is or isn't applicable
     * @throws Exception
     */
    protected function setIpRestrictionSession()
    {
        $app = \JFactory::getApplication();
        if ($app->isAdmin()) {
            return;
        }

        $ipRestriction = 0;
        $ipRestrictionRedirect = null;

        if ($this->checkIpRestricted()) {
            $ipRestriction = 1;
            if ($this->params->get('redirect')) {
                $ipRestrictionRedirect = $this->params->get('redirect');
            }
        }

        $app->setUserState('iprestriction', $ipRestriction);
        $app->setUserState('iprestriction.redirect', $ipRestrictionRedirect);
    }

    /**
     * Check if IP restriction is needed for the current user
     * @return bool
     */
    protected function checkIpRestricted()
    {
        $ipAddressChecks = $this->getIpAddresChecks();

        // no addresses defined, so no check needed
        if (empty($ipAddressChecks)) {
            return false;
        }

        $userIp = $this->getIpAddress();

        if (\IpUtils\Address\IPv4::isValid($userIp)) {
            $ip = new \IpUtils\Address\IPv4($userIp);
        } elseif (\IpUtils\Address\IPv6::isValid($userIp)) {
            $ip = new \IpUtils\Address\IPv6($userIp);
        }

        // no valid IP address found - enable restriction just to be sure
        if (empty($ip)) {
            return true;
        }

        // check if the IP matches any of the specified address patterns

        foreach ($ipAddressChecks as $addressCheck) {

            if (strpos($addressCheck, '/') !== false) {
                // address is a subnet
                $expression = new \IpUtils\Expression\Subnet($addressCheck);
                $match = $ip->matches($expression);

                if ($match === true) {
                    // user's IP checks out - no restriction needed
                    return false;
                }

            } elseif (strpos($addressCheck, '*') !== false) {
                // address is a pattern
                $expression = new \IpUtils\Expression\Pattern($addressCheck);
                $match = $ip->matches($expression);

                if ($match === true) {
                    // user's IP checks out - no restriction needed
                    return false;
                }
            } elseif (
                (($ip instanceof \IpUtils\Address\IPv4) && \IpUtils\Address\IPv4::isValid($addressCheck)) ||
                (($ip instanceof \IpUtils\Address\IPv6) && \IpUtils\Address\IPv6::isValid($addressCheck))
            ) {
                // address is a literal
                $expression = new \IpUtils\Expression\Literal($addressCheck);
                $match = $ip->matches($expression);

                if ($match === true) {
                    // user's IP checks out - no restriction needed
                    return false;
                }
            }
        }

        // user's IP didn't match any specified address patterns, so turn on restriction
        return true;
    }

    /**
     * Get an array with addresses to check for as defined in the plugin's params
     * @return array
     */
    protected function getIpAddresChecks()
    {
        $ipAddressChecks = trim($this->params->get('ipaddresses'));
        $ipAddressChecks = str_replace("\r\n", PHP_EOL, $ipAddressChecks);
        $ipAddressChecks = explode(PHP_EOL, $ipAddressChecks);

        return $ipAddressChecks;
    }

    /**
     * Get the visitor's IP address
     * @return mixed
     */
    protected function getIpAddress()
    {
        if (
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
