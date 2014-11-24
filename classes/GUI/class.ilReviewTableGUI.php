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


include_once 'Services/Table/classes/class.ilTable2GUI.php';

/**
* @author Richard Mörbitz <Richard.Moerbitz@mailbox.tu-dresden.de>
*
* $Id$
*/

class ilReviewTableGUI extends ilTable2GUI {

	public function __construct($a_parent_obj, $a_parent_cmd) {
		global $ilCtrl, $lng;
		parent::__construct($a_parent_obj, $a_parent_cmd);
		$this->addColumn("Titel der Frage", "", "30%");
		$this->addColumn("Autor der Frage", "", "40%");
      $this->addColumn("Aktion", "", "30%");
      $this->setEnableHeader(true);
      $this->setFormAction($ilCtrl->getFormAction($this->getParentObject(), 'showContent'));
     	$this->setRowTemplate("tpl.review_table_row.html", ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'Review')->getDirectory());
      $this->setDefaultOrderField("id");
      $this->setDefaultOrderDirection("asc");
      
      $this->simulateData();
 
      $this->setTitle("Meine Reviews");
	}
	
	private function simulateData() {
		$data = array(
			array("id" => 0, "title" => "Dummy 1 [neu]", "author" => "Hans Wurst", "completed" => 0),
			array("id" => 1, "title" => "Dummy 2 [auch unbearbeitet]", "author" => "Random Name", "completed" => 0),
			array("id" => 2, "title" => "Dummy 3 [fertiggestellt]", "author" => "Max Mustermann", "completed" => 1)				
		);
		$this->setData($data);
	}
	
	/*
	* Fill a single data row
	*/
	protected function fillRow($a_set) {
		global $ilCtrl, $lng;
		$this->tpl->setVariable("TXT_TITLE", $a_set["title"]);
		$this->tpl->setVariable("TXT_AUTHOR", $a_set["author"]);
		if ($a_set["completed"]) {
			$this->tpl->setVariable("TXT_ACTION", "Ansehen");
			$this->tpl->setVariable("LINK_ACTION", $ilCtrl->getLinkTargetByClass("ilObjReviewGUI", "showReviews"));
		}
		else {
			$this->tpl->setVariable("TXT_ACTION", "Erstellen");
			$this->tpl->setVariable("LINK_ACTION", $ilCtrl->getLinkTargetByClass("ilObjReviewGUI", "inputReview"));
		}
	}
}

?>