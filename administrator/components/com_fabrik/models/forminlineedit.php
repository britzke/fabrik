<?php
/**
 * Fabrik Form Model
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2015 fabrikar.com - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

namespace Fabrik\Admin\Models;

// No direct access
defined('_JEXEC') or die('Restricted access');

use \Fabrik\Admin\Models\Base as Base;
use Fabrik\Admin\Models\Form;

/**
 * Fabrik Form Model
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @since       3.5
 */
class FormInlineEdit extends Base
{
	/**
	 * Render the inline edit interface
	 *
	 * @return void
	 */
	public function render()
	{
		$this->formModel = new Form;
		$input = $this->app->input;

		// Need to render() with all element ids in case canEditRow plugins etc. use the row data.
		$elids = $input->get('elementid', array(), 'array');
		$input->set('elementid', null);

		$form = $this->formModel->getForm();
		$this->formModel->render();

		// Set back to original input so we only show the requested elements
		$input->set('elementid', $elids);
		$this->groups = $this->formModel->getGroupView();

		// Main trigger element's id
		$elementId = $input->getInt('elid');

		$html = $this->inlineEditMarkUp() ;
		echo implode("\n", $html);

		$srcs = array();
		$repeatCounter = 0;
		$elementIds = (array) $input->get('elementid', array(), 'array');
		$eCounter = 0;
		$onLoad = array();
		$onLoad[] = "Fabrik.inlineedit_$elementId = {'elements': {}};";

		foreach ($elementIds as $id)
		{
			$elementModel = $this->formModel->getElement($id, true);
			$elementModel->getElement();
			$elementModel->setEditable(true);
			$elementModel->formJavascriptClass($srcs);
			$elementJS = $elementModel->elementJavascript($repeatCounter);
			$onLoad[] = 'var o = new ' . $elementJS[0] . '("' . $elementJS[1] . '",' . json_encode($elementJS[2]) . ');';

			if ($eCounter === 0)
			{
				$onLoad[] = "o.select();";
				$onLoad[] = "o.focus();";
				$onLoad[] = "Fabrik.inlineedit_$elementId.token = '" . JSession::getFormToken() . "';";
			}

			$eCounter++;
			$onLoad[] = "Fabrik.inlineedit_$elementId.elements[$id] = o";
		}

		$onLoad[] = "Fabrik.fireEvent('fabrik.list.inlineedit.setData');";
		FabrikHelperHTML::script($srcs, implode("\n", $onLoad));
	}

	/**
	 * Create markup for bootstrap inline editor
	 *
	 * @since   3.1b
	 *
	 * @return  array
	 */
	protected function inlineEditMarkUp()
	{
		$input = $this->app->input;
		$html = array();
		$html[] = '<div class="modal">';
		$html[] = ' <div class="modal-header"><h3>' . FText::_('COM_FABRIK_EDIT') . '</h3></div>';
		$html[] = '<div class="modal-body">';
		$html[] = '<form>';

		foreach ($this->groups as $group)
		{
			foreach ($group->elements as $element)
			{
				$html[] = '<div class="control-group fabrikElementContainer ' . $element->id . '">';
				$html[] = '<label>' . $element->label . '</label>';
				$html[] = '<div class="fabrikElement">';
				$html[] = $element->element;
				$html[] = '</div>';
				$html[] = '</div>';
			}
		}

		$html[] = '</form>';
		$html[] = '</div>';

		if ($input->getBool('inlinesave') || $input->getBool('inlinecancel'))
		{
			$html[] = '<div class="modal-footer">';

			if ($input->getBool('inlinecancel') == true)
			{
				$html[] = '<a href="#" class="btn inline-cancel">';
				$html[] = FabrikHelperHTML::image('delete.png', 'list', @$this->tmpl, array('alt' => FText::_('COM_FABRIK_CANCEL')));
				$html[] = '<span>' . FText::_('COM_FABRIK_CANCEL') . '</span></a>';
			}

			if ($input->getBool('inlinesave') == true)
			{
				$html[] = '<a href="#" class="btn btn-primary inline-save">';
				$html[] = FabrikHelperHTML::image('save.png', 'list', @$this->tmpl, array('alt' => FText::_('COM_FABRIK_SAVE')));
				$html[] = '<span>' . FText::_('COM_FABRIK_SAVE') . '</span></a>';
			}

			$html[] = '</div>';
		}

		$html[] = '</div>';

		return $html;
	}

	/**
	 * Set form model
	 *
	 * @param   JModel  $model  Front end form model
	 *
	 * @return  void
	 */
	public function setFormModel($model)
	{
		$this->formModel = $model;
	}

	/**
	 * Inline edit show the edited element
	 *
	 * @return string
	 */
	public function showResults()
	{
		$input = $this->app->input;
		$listModel = $this->formModel->getListModel();
		$listId = $listModel->getId();
		$listModel->clearCalculations();
		$listModel->doCalculations();
		$elementId = $input->getInt('elid');

		if ($elementId === 0)
		{
			return;
		}

		$elementModel = $this->formModel->getElement($elementId, true);

		if (!$elementModel)
		{
			return;
		}

		$rowId = $input->get('rowid');
		$listModel->setId($listId);

		// If the inline edit stored a element join we need to reset back the table
		$listModel->clearTable();
		$listModel->getTable();
		$data = $listModel->getRow($rowId);

		// For a change in the element which means its no longer shown in the list due to prefilter. We may want to remove the row from the list as well?
		if (!is_object($data))
		{
			$data = new stdClass;
		}

		$key = $input->get('element');
		$html = '';
		$html .= $elementModel->renderListData($data->$key, $data);
		$listRef = 'list_' . $input->get('listref');
		$doCalcs = "\nFabrik.blocks['" . $listRef . "'].updateCals(" . json_encode($listModel->getCalculations()) . ")";
		$html .= '<script type="text/javascript">';
		$html .= $doCalcs;
		$html .= "</script>\n";

		return $html;
	}
}