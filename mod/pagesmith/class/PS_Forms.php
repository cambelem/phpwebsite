<?php
  /**
   * @version $Id$
   * @author Matthew McNaney <mcnaney at gmail dot com>
   */

class PS_Forms {
    public $template = null;
    public $ps       = null;
    public $tpl_list = null;


    public function editPage()
    {
        if (!$this->ps->page->id) {
            if (!empty($this->ps->page->_tpl)) {
                $this->pageLayout();
            } elseif (isset($_GET['fname'])) {
                $this->pickTemplate();
            } else {
                $this->pickFolder();
            }
            return;
        }
    }

    public function loadTemplates()
    {
        PHPWS_Core::initModClass('pagesmith', 'PS_Template.php');
        if (!empty($this->tpl_list)) {
            return true;
        }

        $tpl_dir = $this->ps->pageTplDir();
        $templates = PHPWS_File::listDirectories($tpl_dir);

        if (empty($templates)) {
            PHPWS_Error::log(PS_TPL_DIR, 'pagesmith', 'PS_Forms::loadTemplates', $tpl_dir);
            return false;
        }

        foreach ($templates as $tpl) {
            $pg_tpl = new PS_Template($tpl);
            if ($pg_tpl->data) {
                $this->tpl_list[$tpl] = $pg_tpl;
            }
        }
        return true;
    }

    public function pageLayout()
    {
        $page = $this->ps->page;

        $pg_tpl_name = & $page->_tpl->name;
        $this->ps->killSaved();

        $form = new PHPWS_Form('pagesmith');
        $form->addHidden('module', 'pagesmith');
        $form->addHidden('aop', 'post_page');
        $form->addHidden('tpl', $page->template);

        $form->addText('title', $page->title);
        $form->setSize('title', 40, 255);
        $form->setExtra('title', 'onchange="update_title()"');
        $form->setLabel('title', dgettext('pagesmith', 'Page title'));

        if ($page->id) {
            $this->ps->title = dgettext('pagesmith', 'Update page');
            $form->addHidden('id', $page->id);
        } else {
            $this->ps->title = dgettext('pagesmith', 'Create page');
        }

        if (empty($page->_tpl) || $page->_tpl->error) {
            $this->ps->content = dgettext('pagesmith', 'Unable to load page template.');
            return;
        }
        $form->addSubmit('submit', dgettext('pagesmith', 'Save page'));
        $this->pageTemplateForm($form);
        $tpl = $form->getTemplate();
        $jsvars['page_title_input'] = 'pagesmith_title';
        $jsvars['page_title_id'] = sprintf('%s-page-title', $pg_tpl_name);
        javascript('modules/pagesmith/pagetitle', $jsvars);

        $this->ps->content = PHPWS_Template::process($tpl, 'pagesmith', 'page_form.tpl');
    }


    public function editPageHeader()
    {
        $section_name = $_GET['section'];

        $vars['parent_section'] = 'pagesmith_' . $section_name;
        $vars['edit_input']     = 'edit_header';
        $vars['url']            = PHPWS_Core::getHomeHttp();
        javascript('modules/pagesmith/passinfo', $vars);

        $form = new PHPWS_Form('edit');
        $form->addHidden('tpl', $_GET['tpl']);
        $form->addHidden('module', 'pagesmith');
        $form->addHidden('aop', 'post_header');
        $form->addHidden('section_name', $section_name);
        $form->addText('header');
        $form->setLabel('header', dgettext('pagesmith', 'Header'));
        $form->setSize('header', 40);
        $form->addSubmit(dgettext('pagesmith', 'Update'));
        $tpl = $form->getTemplate();

        $tpl['CANCEL'] = javascript('close_window');
        $this->ps->title = dgettext('pagesmith', 'Edit header');
        $this->ps->content = PHPWS_Template::process($tpl, 'pagesmith', 'edit_header.tpl');
    }

    public function editPageText()
    {
        $section_name = $_GET['section'];

        $vars['parent_section'] = 'pagesmith_' . $section_name;
        $vars['edit_input']     = 'edit_text';
        $vars['url']            = PHPWS_Core::getHomeHttp();
        javascript('modules/pagesmith/passinfo', $vars);

        $form = new PHPWS_Form('edit');
        $form->addHidden('tpl', $_GET['tpl']);
        $form->addHidden('module', 'pagesmith');
        $form->addHidden('aop', 'post_text');
        $form->addHidden('section_name', $section_name);
        $form->addTextArea('text');
        $form->useEditor('text', true, false, 720, 480);
        $form->addSubmit(dgettext('pagesmith', 'Update'));
        $tpl = $form->getTemplate();
        $tpl['CANCEL'] = javascript('close_window');
        $this->ps->title = dgettext('pagesmith', 'Edit text');
        $this->ps->content = PHPWS_Template::process($tpl, 'pagesmith', 'edit_text.tpl');
    }


    public function pageList()
    {
        PHPWS_Core::initCoreClass('DBPager.php');
        PHPWS_Core::initModClass('pagesmith', 'PS_Page.php');

        $pgtags['ACTION_LABEL']  = dgettext('pagesmith', 'Action');

        $pager = new DBPager('ps_page', 'PS_Page');
        $pager->addPageTags($pgtags);
        $pager->setModule('pagesmith');
        $pager->setTemplate('page_list.tpl');
        $pager->addRowTags('row_tags');
        $pager->setEmptyMessage(dgettext('pagesmith', 'No pages have been created.'));
        $pager->setSearch('title');
        $pager->addSortHeader('id', dgettext('pagesmith', 'Id'));
        $pager->addSortHeader('title', dgettext('pagesmith', 'Title'));
        $pager->addSortHeader('create_date', dgettext('pagesmith', 'Created'));
        $pager->addSortHeader('last_updated', dgettext('pagesmith', 'Updated'));

        $this->ps->title   = dgettext('pagesmith', 'Pages');
        $this->ps->content = $pager->get();
    }


    public function pageTemplateForm(PHPWS_Form $form)
    {
        $page = & $this->ps->page;

        $page->_tpl->loadStyle();
        $vars['id'] = $page->id;
        $vars['tpl'] = $page->template;

        foreach ($page->_sections as $name=>$section) {
            $form->addHidden('sections', $name);
            $content = $section->getContent();
            if (empty($content)) {
                $tpl[$name] = '&nbsp;';
            } else {
                $tpl[$name] = $content;
            }

            switch ($section->sectype) {
            case 'header':
                $js['label'] = dgettext('pagesmith', 'Edit header');
                $js['link_title'] = dgettext('pagesmith', 'Change header');
                $vars['aop'] = 'edit_page_header';
                $js['width'] = 400;
                $js['height'] = 200;
                $edit_button = true;
                break;

            case 'text':
                $js['label'] = dgettext('pagesmith', 'Edit text');
                $js['link_title'] = dgettext('pagesmith', 'Change text');
                $vars['aop'] = 'edit_page_text';
                $js['width'] = 800;
                $js['height'] = 600;
                $edit_button = true;
                break;
            }

            if ($edit_button) {
                $vars['section'] = $name;
                //                $js['type'] = 'button';
                $js['label']   = PS_EDIT;
                $js['address'] = PHPWS_Text::linkAddress('pagesmith', $vars, 1);
                $tpl[$name . '_edit'] = javascript('open_window', $js);

                // section session?
                if ($page->id) {
                    $form->addHidden($name, htmlspecialchars($section->content));
                } else {
                    $form->addHidden($name, '');
                }
            }
        }

        $template_file = $page->_tpl->page_path . 'page.tpl';

        if (empty($page->title)) {
            $tpl['page_title'] = dgettext('pagesmith', 'Page Title (edit above)');
        } else {
            $tpl['page_title'] = $page->title;
        }

        $pg_tpl =  PHPWS_Template::process($tpl, 'pagesmith', $template_file);

        $form->addTplTag('PAGE_TEMPLATE', $pg_tpl);
    }

    public function pickTemplate()
    {
        Layout::addStyle('pagesmith');
        $this->ps->title = dgettext('pagesmith', 'Pick a template');
        $this->loadTemplates();

        if (empty($this->tpl_list)) {
            $this->ps->content = dgettext('pagesmith', 'Could not find any page templates. Please check your error log.');
        }

        @$fname = $_GET['fname'];

        foreach ($this->tpl_list as $pgtpl) {
            if ($fname && !empty($pgtpl->folders) && !in_array($fname, $pgtpl->folders)) {
                continue;
            }
            $tpl['page-templates'][] = $pgtpl->pickTpl();
        }

        $tpl['BACK'] = PHPWS_Text::secureLink(dgettext('pagesmith', 'Back to style selection'), 'pagesmith', array('aop'=>'menu', 'tab'=>'new'));
        $this->ps->content = PHPWS_Template::process($tpl, 'pagesmith', 'pick_template.tpl');
    }

    public function pickFolder()
    {
        @include 'config/pagesmith/folder_icons.php';
        $folder_list = array();

        Layout::addStyle('pagesmith');
        $this->loadTemplates();
        foreach ($this->tpl_list as $template) {
            if ($template->folders) {
                foreach ($template->folders as $folder_name) {
                    @$folder_list[$folder_name]++;
                }
            }
        }

        $vars['aop'] = 'menu';
        foreach ($folder_list as $name=>$count) {
            $vars['fname'] = $name;
            $image = @$folder_icon[$name];
            if (!$image) {
                $image = 'folder_contents.png';
            }
            $link = PHPWS_Text::linkAddress('pagesmith', $vars, true);
            $tpl['folders'][] = array('TITLE' => ucwords(str_replace('-', '&nbsp;', $name)),
                                      'IMAGE' => sprintf('<a href="%s"><img src="images/mod/pagesmith/folder_icons/%s" /></a>', $link, $image),
                                      'COUNT' => sprintf(dngettext('pagesmith', '%s template', '%s templates', $count), $count));
        }

        $this->ps->title = dgettext('pagesmith', 'Choose a style');
        $this->ps->content = PHPWS_Template::process($tpl, 'pagesmith', 'pick_folder.tpl');
    }

    public function settings()
    {
        $form = new PHPWS_Form('ps-settings');
        $form->addHidden('module', 'pagesmith');
        $form->addHidden('aop', 'post_settings');
        $form->addSubmit(dgettext('pagesmith', 'Save'));

        $form->addCheck('auto_link', 1);
        $form->setMatch('auto_link', PHPWS_Settings::get('pagesmith', 'auto_link'));
        $form->setLabel('auto_link', dgettext('pagesmith', 'Add menu link for new pages.'));

        $this->ps->title = dgettext('pagesmith', 'PageSmith Settings');
        $this->ps->content = PHPWS_Template::process($form->getTemplate(), 'pagesmith', 'settings.tpl');
    }

    public function uploadTemplates()
    {
        $this->ps->title = dgettext('pagesmith', 'Upload template');

        if (!is_writable('templates/pagesmith/page_templates/')) {
            $this->ps->content = dgettext('pagesmith', 'Page template directory must be writable to upload templates.');
            return;
        }
        $form = new PHPWS_Form('upload-templates');
        $form->addHidden('module', 'pagesmith');
        $form->addHidden('aop', 'post_templates');
        $form->addText('template_name', @$_POST['template_name']);
        $form->setLabel('template_name', dgettext('pagesmith', 'Template name'));

        $form->addFile('template_file');
        $form->setLabel('template_file', dgettext('pagesmith', 'Template file (e.g., filename.tpl)'));

        $form->addFile('style_sheet');
        $form->setLabel('style_sheet', dgettext('pagesmith', 'Style sheet (e.g., filename.css)'));

        $form->addFile('icon');
        $form->setLabel('icon', dgettext('pagesmith', 'Template icon (e.g., filename.png)'));

        $form->addFile('structure_file');
        $form->setLabel('structure_file', _('Structure file (e.g., structure.xml)'));

        $form->addSubmit('upload', dgettext('pagesmith', 'Upload file'));

        $this->ps->content = PHPWS_Template::process($form->getTemplate(), 'pagesmith', 'upload_template.tpl');
    }
}

?>