<?php

class SV_ReportImprovements_Model_WarningLog extends XenForo_Model
{
    const Operation_EditWarning = 'edit';
    const Operation_DeleteWarning = 'delete';
    const Operation_ExpireWarning = 'expire';
    const Operation_NewWarning = 'new';

    /**
     * Gets the specified Warning Log if it exists.
     *
     * @param string $warningLogId
     *
     * @return array|false
     */
    public function getWarningLogById($warningLogId)
    {
        return $this->_getDb()->fetchRow('
            SELECT *
            FROM xf_sv_warning_log
            WHERE warning_log_id = ?
        ', $warningLogId);
    }

    public function canCreateReportFor($contentType)
    {
        $WarningHandler = $this->_getWarningModel()->getWarningHandler($contentType);
        $ReportHandler = $this->_getReportModel()->getReportHandler($contentType);
        return !empty($WarningHandler) && !empty($ReportHandler);
    }

    public function getContentForReportFromWarning(array $warning)
    {
        $contentType = $warning['content_type'];
        $contentId = $warning['content_id'];
        $handler = $this->_getWarningModel()->getWarningHandler($contentType);
        if (empty($handler))
        {
            return false;
        }
        return $handler->getContent($contentId);
    }

    /**
     * Gets the specified Warning Log if it exists.
     *
     * @param string $operationType
     * @param array $warning
     *
     * @return int|false
     */
    public function LogOperation($operationType, array $warning, $ImporterMode = false)
    {
        if (@$operationType == '')
            throw new Exception("Unknown operation type when logging warning");

        unset($warning['warning_log_id']);
        $warning['operation_type'] = $operationType;
        $warning['warning_edit_date'] = XenForo_Application::$time;

        $warningLogDw = XenForo_DataWriter::create('SV_ReportImprovements_DataWriter_WarningLog');
        $warningLogDw->bulkSet($warning);
        $warningLogDw->save();
        $warning['warning_log_id'] = $warningLogDw->get('warning_log_id');

        if (SV_ReportImprovements_Globals::$SupressLoggingWarningToReport)
        {
            return $warning['warning_log_id'];
        }

        $options = XenForo_Application::getOptions();
        if ($options->reportIntoForumId)
        {
            return $warning['warning_log_id'];
        }

        $reportModel = $this->_getReportModel();
        $reportUser = XenForo_Visitor::getInstance()->toArray();
        if (!empty(SV_ReportImprovements_Globals::$OverrideReportUserId) || $reportUser['user_id'] == 0 || $reportUser['username'] == '')
        {
            $reportUser = $this->_getUserModel()->getUserById(SV_ReportImprovements_Globals::$OverrideReportUserId);
            if (empty($reportUser))
            {
                return $warning['warning_log_id'];
            }
        }

        if (!empty(SV_ReportImprovements_Globals::$OverrideReportUsername))
        {
            $reportUser['username'] = SV_ReportImprovements_Globals::$OverrideReportUsername;
        }

        $commentToUpdate = null;

        $report = $reportModel->getReportByContent($warning['content_type'], $warning['content_id']);
        if($report || $options->sv_report_new_warnings)
        {
            $content = $this->getContentForReportFromWarning($warning);
            if (!empty($content))
            {
                $this->createReportContent($report, $warning, $warning['content_type'], $content, $reportUser, $ImporterMode);
            }
        }

        return $warning['warning_log_id'];
    }

    public function createReportContent(array $report, array $warning, $contentType, array $content, array $viewingUser = null, $ImporterMode = false)
    {
        $this->standardizeViewingUserReference($viewingUser);

        if (!$viewingUser['user_id'])
        {
            return false;
        }

        $handler = $this->_getReportModel()->getReportHandler($contentType);
        if (!$handler)
        {
            return false;
        }

        list($contentId, $contentUserId, $contentInfo) = $handler->getReportDetailsFromContent($content);
        if (!$contentId)
        {
            return false;
        }

        if (empty($report))
        {
            $reportDw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
            $reportDw->bulkSet(array(
                'content_type' => $contentType,
                'content_id' => $contentId,
                'content_user_id' => $contentUserId,
                'content_info' => $contentInfo
            ));
            $reportDw->save();
            $report = $reportDw->getMergedData();
        }

        if ($ImporterMode)
        {
            $reportDw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
            $reportDw->setExistingData($report['report_id']);
            $reportDw->set('first_report_date', $warning['warning_date']);
            $reportDw->set('last_modified_date', $warning['warning_date']);
            $reportDw->setImportMode(true);
            $reportDw->save();
            $report['first_report_date'] = $report['last_modified_date'] = $warning['warning_date'];
            $reasonDw->set('comment_date', $warning['warning_date']);
            $reasonDw->setImportMode(true);
        }

        $reasonDw = XenForo_DataWriter::create('XenForo_DataWriter_ReportComment');
        $reasonDw->setOption(SV_ReportImprovements_XenForo_DataWriter_ReportComment::OPTION_WARNINGLOG_REPORT, $report);
        $reasonDw->setOption(SV_ReportImprovements_XenForo_DataWriter_ReportComment::OPTION_WARNINGLOG_WARNING, $warning);
        if ($ImporterMode)
        {
            $reasonDw->set('comment_date', $warning['warning_date']);
            $reasonDw->setImportMode(true);
        }
        $reasonDw->bulkSet(array(
            'report_id' => $report['report_id'],
            'user_id' => $viewingUser['user_id'],
            'username' => $viewingUser['username'],
            'warning_log_id' => $warning['warning_log_id'],
        ));
        $reasonDw->save();

        return $report['report_id'];
    }

    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }

    protected function _getReportModel()
    {
        return $this->getModelFromCache('XenForo_Model_Report');
    }

    protected function _getWarningModel()
    {
        return $this->getModelFromCache('XenForo_Model_Warning');
    }
}