<?php

class SV_IntegratedReports_XenForo_Model_Warning extends XFCP_SV_IntegratedReports_XenForo_Model_Warning
{
    public function processExpiredWarnings()
    {
        SV_IntegratedReports_Model_WarningLog::$UseSystemUsernameForComments = true;
        $options = XenForo_Application::getOptions();
        if ($options->sv_ir_log_to_report_natural_warning_expire)
        {
            SV_IntegratedReports_Model_WarningLog::$SupressLoggingWarningToReport = true;
        }
        try
        {
            parent::processExpiredWarnings();
        }
        finally
        {
            SV_IntegratedReports_Model_WarningLog::$UseSystemUsernameForComments = false;
            SV_IntegratedReports_Model_WarningLog::$SupressLoggingWarningToReport = false;
        }
    }
}