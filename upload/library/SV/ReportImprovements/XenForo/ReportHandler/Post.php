<?php

class SV_ReportImprovements_XenForo_ReportHandler_Post extends XFCP_SV_ReportImprovements_XenForo_ReportHandler_Post
{
    public function getVisibleReportsForUser(array $reports, array $viewingUser)
    {
        $reportsByForum = array();
        foreach ($reports AS $reportId => $report)
        {
            $info = unserialize($report['content_info']);
            $reportsByForum[$info['node_id']][] = $reportId;
        }

        $forumModel = $this->_getForumModel();
        $forums = $forumModel->getForumsByIds(array_keys($reportsByForum), array(
            'permissionCombinationId' => $viewingUser['permission_combination_id']
        ));
        $forums = $forumModel->unserializePermissionsInList($forums, 'node_permission_cache');

        foreach ($reportsByForum AS $forumId => $forumReports)
        {
            if (!isset($forums[$forumId]) ||
                !$forumModel->canManageReportedMessage($forums[$forumId], $errorPhraseKey, $viewingUser)
            )
            {
                foreach ($forumReports AS $reportId)
                {
                    unset($reports[$reportId]);
                }
            }
        }

        return $reports;
    }

    public function viewCallback(XenForo_View $view, array &$report, array &$contentInfo)
    {
        /* @var $postModel XenForo_Model_Post */
        $postModel = XenForo_Model::create('XenForo_Model_Post');

        $message = $postModel->getPostById($report['content_id']);
        $posts = $postModel->getAndMergeAttachmentsIntoPosts(array($message['post_id'] => $message));
        $message = reset($posts);

        if (!empty($message['attachments']))
        {
            /* @var $reportModel SV_ReportImprovements_XenForo_Model_Report */
            $reportModel = XenForo_Model::create('XenForo_Model_Report');
            foreach ($message['attachments'] as &$attachment)
            {
                $attachment['reportKey'] = $reportModel->getAttachmentReportKey($attachment);
            }
            $contentInfo['attachments'] = $message['attachments'];
            $contentInfo['attachments_count'] = count($message['attachments']);
        }
        if (isset($message['post_date']))
        {
            $contentInfo['content_date'] = $message['post_date'];
        }

        $template = parent::viewCallback($view, $report, $contentInfo);

        if (!empty($message['attachments']))
        {
            $class = XenForo_Application::resolveDynamicClass('SV_ReportImprovements_AttachmentParser');
            $template->setParam('bbCodeParser', new $class($template->getParam('bbCodeParser'), $report, $contentInfo));
            // trim excess attachments
            $content = $template->getParam('content');
            if (!empty($content['attachments']))
            {
                if (stripos($content['message'], '[/attach]') !== false)
                {
                    if (preg_match_all('#\[attach(=[^\]]*)?\](?P<id>\d+)(\D.*)?\[/attach\]#iU', $content['message'], $matches))
                    {
                        foreach ($matches['id'] AS $attachId)
                        {
                            unset($content['attachments'][$attachId]);
                        }
                    }
                }
            }
            $template->setParam('content', $content);
        }

        return $template;
    }

    protected $_forumModel = null;

    /**
     * @return SV_ReportImprovements_XenForo_Model_Forum
     */
    protected function _getForumModel()
    {
        if (empty($this->_forumModel))
        {
            $this->_forumModel = XenForo_Model::create('XenForo_Model_Forum');
        }

        return $this->_forumModel;
    }
}

// ******************** FOR IDE AUTO COMPLETE ********************
if (false)
{
    class XFCP_SV_ReportImprovements_XenForo_ReportHandler_Post extends XenForo_ReportHandler_Post {}
}