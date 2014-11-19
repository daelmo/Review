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


include_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
include_once 'Services/Table/classes/class.ilTable2GUI.php';

/**
* @author Richard Mörbitz <Richard.Moerbitz@mailbox.tu-dresden.de>
*
* $Id$
*/

class ilReviewInputGUI extends ilPropertyFormGUI {
	private $a_parent_obj;
	private $a_parent_cmd;
	
	public function __construct($a_parent_obj, $a_parent_cmd) {
		global $ilCtrl;
		parent::__construct();
		
		$this->a_parent_obj = $a_parent_obj;
		$this->a_parent_cmd = $a_parent_cmd;
		
		$this->setTitle("Review-Eingabeformular");
		$this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
		
		$this->populateQuestionFormPart();
		$this->populateReviewFormPart();
		$this->populateTaxonomyFormPart();
		$this->populateEvaluationFormPart();
		$this->populateAdditionalData();		
		
		$this->addCommandButton($ilCtrl->getFormAction($a_parent_obj), "Absenden");
		//$this->addCommandButton($ilCtrl->getLinkTargetByClass($a_parent_obj, $a_parent_cmd), "Abbrechen");
	}
	
	private function populateQuestionFormPart() {
		$head_q = new ilFormSectionHeaderGUI();
		$head_q->setTitle("Frage");
		$this->addItem($head_q);
		
		$title = new ilNonEditableValueGUI("Titel");
		$title->setValue($this->simulateData()["title"]);
		$this->addItem($title);
		
		$description = new ilNonEditableValueGUI("Beschreibung");
		$description->setValue($this->simulateData()["description"]);
		$this->addItem($description);
		
		$question = new ilNonEditableValueGUI("Fragestellung");
		$question->setValue($this->simulateData()["question"]);
		$this->addItem($question);
		
		$dir = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'Review')->getDirectory();

		$answers = $this->createAnswers($this->simulateData()["answers"]);
		$this->addItem($answers);
	}
	
	private function populateReviewFormPart() {
		$head_r = new ilFormSectionHeaderGUI();
		$head_r->setTitle("Review");
		$this->addItem($head_r);
		
		$eva_head = $this->createHead();
		$this->addItem($eva_head);	
		
		$eva_desc = $this->createAspect("Beschreibung", "d");
		$this->addItem($eva_desc);
		
		$eva_quest = $this->createAspect("Fragestellung", "q");
		$this->addItem($eva_quest);
		
		$eva_answ = $this->createAspect("Antworten", "a");
		$this->addItem($eva_answ);
	}
	
	private function populateTaxonomyFormPart() {
		$head_t = new ilFormSectionHeaderGUI();
		$head_t->setTitle("Taxonomiestufe und Wissensdimension");
		$this->addItem($head_t);
		
		$cog_a = new ilSelectInputGUI("Taxonomiestufe Autor", "cog_a");
		$cog_a->setValue($this->simulateData()["cog"]);
		$cog_a->setOptions($this->cognitiveProcess());
		$cog_a->setDisabled(true);
		$this->addItem($cog_a);
		
		$kno_a = new ilSelectInputGUI("Wissensdimension Autor", "kno_a");
		$kno_a->setValue($this->simulateData()["kno"]);
		$kno_a->setOptions($this->knowledge());
		$kno_a->setDisabled(true);
		$this->addItem($kno_a);
		
		$cog_r = new ilSelectInputGUI("Taxonomiestufe Reviewer", "cog_r");
		$cog_r->setRequired(true);
		$cog_r->setValue(0);
		$cog_r->setOptions($this->cognitiveProcess());
		$this->addItem($cog_r);
		
		$kno_r = new ilSelectInputGUI("Wissensdimension Reviewer", "kno_r");
		$kno_r->setRequired(true);
		$kno_r->setValue(0);
		$kno_r->setOptions($this->knowledge());
		$this->addItem($kno_r);
	}
		
	private function populateEvaluationFormPart() {
		$head_e = new ilFormSectionHeaderGUI();
		$head_e->setTitle("Bewertung");
		$this->addItem($head_e);

		$group_e = new ilRadioGroupInputGUI("Urteil" ,"group_e");
		$op_a = new ilRadioOption("Frage akzeptiert", "1", "");
		$group_e->addOption($op_a);
		$op_e = new ilRadioOption("Frage überarbeiten", "2", "");
		$group_e->addOption($op_e);
		$op_d = new ilRadioOption("Frage abgelehnt", "3", "");
		$group_e->addOption($op_d);
		$this->addItem($group_e);

		$comment = new ilTextAreaInputGUI("Bemerkungen", "comment");
		$comment->setCols(70);
		$comment->setRows(10);
		$this->addItem($comment);
	}
		
	private function populateAdditionalData() {
		$head_a = new ilFormSectionHeaderGUI();
		$head_a->setTitle("Weitere Informationen");
		$this->addItem($head_a);
		
		$author = new ilNonEditableValueGUI("Autor der Frage");
		$author->setValue($this->simulateData()["author"]);
		$this->addItem($author);
	}
	
	private function createHead() {
		$eva_head = new ilCustomInputGUI();
		$html = 
			'<table border="0" cellpadding="20">
				<tr>
					<td align="center" width="130" height="16">Fachl. Richtigkeit</td>
					<td align="center" width="130">Relevanz</td>
					<td align="center" width="130">Formulierung</td>
				</tr>
			</table>';
		$eva_head->setHTML($html);
		return $eva_head;
	}
	
	private function createAspect($aspect, $abbr) {
		$eva_row = new ilCustomInputGUI();
		$eva_row->setTitle($aspect);
		$html = sprintf(
			'<table border="0" cellpadding="20">
				<tr>
					<td align="center" width="130" height="16">
						<select name="%cc">
							<option value="nil"></option>
							<option value="good">gut</option>
							<option value="correct">Korrektur</option>
							<option value="refused">ungeeignet</option>
						</select>
					</td>
					<td align="center" width="130">
						<select name="%cr">
							<option value="nil"></option>
							<option value="good">gut</option>
							<option value="correct">Korrektur</option>
							<option value="refused">ungeeignet</option>
						</select>
					</td>
					<td align="center" width="130">
						<select name="%ce">
							<option value="nil"></option>
							<option value="good">gut</option>
							<option value="correct">Korrektur</option>
							<option value="refused">ungeeignet</option>
						</select>
					</td>
				</tr>
			</table>',
			$abbr, $abbr, $abbr
		);	
		$eva_row->setHTML($html);
		return $eva_row;
	}
	
	private function createAnswers($answers) {
		$answ_list = new ilCustomInputGUI();
		$answ_list->setTitle("Antwortoptionen");
		$html = "<ul>";
		foreach ($answers as $answer)
			$html .= "<li>" . $answer["answer"] . "</li>";
		$html .= "</ul>";
		$answ_list->setHTML($html);
		return $answ_list;
	}

	private function simulateData() {
		$data = array("answers" => array(
													array("id" => 0, "answer" => "42"),
													array("id" => 1, "answer" => "zweiundvierzig"),
													array("id" => 2, "answer" => "forty two")
												  ),
						  "title" => "Dummy-Titel",
						  "question" => "Ist diese Dummy-Frage eine Dummy-Frage?",
						  "description" => "Dummy-Beschreibung der zu diesem Dummy-Review gehörigen Dummy-Frage",
						  "author" => "Dummy Autor",
						  "cog" => 2,
						  "kno" => 3
						 );
		return $data;
	}
	
	private function cognitiveProcess() {
		return array(0 => "",
						 1 => "Remember",
						 2 => "Understand",
						 3 => "Apply",
						 4 => "Analyze",
						 5 => "Evaluate",
						 6 => "Create",
						);
	}
	
	private function knowledge() {
		return array(0 => "",
						 1 => "Conceptual",
						 2 => "Factual",
						 3 => "Procedural",
						 4 => "Metacognitive",
						);
	}
} 

?>