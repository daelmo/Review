<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2009 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/

/**
* @author Richard Mörbitz <Richard.Moerbitz@mailbox.tu-dresden.de>
*
* $Id$
*/

class ilCheckMatrixRowGUI extends ilCustomInputGUI {
	
	private $postvars;
	private $question_id;
	
	/**
	* Constructor for table-like display of ilSelectInputGUIs
	*
	* @param	integer		$count		number of checkboxes per row
	* @param	array		$question	associative array = question record
	*/
	public function __construct($question, $reviewer_ids) {
		parent::__construct();
		$this->reviewer_ids = array();
		$this->question_id = $question["id"];
		foreach ($reviewer_ids as $reviewer_id)
			$this->postvars[$reviewer_id] = sprintf("id_%s", $reviewer_id);
		$path_to_il_tpl = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'Review')->getDirectory();
		$custom_tpl = new ilTemplate("tpl.matrix_row.html", true, true, $path_to_il_tpl);
		$custom_tpl->addCss('./Customizing/global/plugins/Services/Repository/RepositoryObject/Review/templates/css/Review.css');
		foreach ($this->postvars as $postvar) {
			$chbox = new ilCheckboxInputGUI("", $postvar);
			$chbox->insert($custom_tpl);
		}
		$this->setTitle($question["title"]);
		$this->setHTML($custom_tpl->get());	
	}
	
	public function getPostVars() {
		return $this->postvars;
	}
	
	public function getQuestionId() {
		return $this->question_id;
	}
}