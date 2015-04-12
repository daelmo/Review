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

/*
 * GUI, a row of checkboxes in the question-reviewer allocation matrix
 *
 * @var		array		$postvars		$_POST variables of each checkbox
 * @var		integer	    $row_id	        id of the row's corresponding object
 *
 * @author Richard Mörbitz <Richard.Moerbitz@mailbox.tu-dresden.de>
 *
 * $Id$
 */

class ilCheckMatrixRowGUI extends ilCustomInputGUI {
    private $checkboxes;
	private $postvars;
	private $row_id;

	/*
	 * Constructor for a line in a table-like display of ilCheckboxInputGUIs
	 *
     * @param   integer     $group_id       id of the whole matrix
	 * @param	array		$object 		object behind a row
     *                                      has a name and an title
	 * @param	array		$column_ids     ids of the objects belonging to each checkbox
	 */
	public function __construct($group_id = 0, $object, $column_ids) {

		parent::__construct();

        $this->group_id = $group_id;
		$this->row_id = $object->id;
		$this->postvars = array();
		foreach ($column_ids as $column_id) {
            $this->postvars[$column_id] = sprintf(
                "id_%s_%s_%s", $this->group_id, $this->row_id, $column_id);
        }

		foreach ($this->postvars as $postvar) {
			$chbox = new ilCheckboxInputGUI("", $postvar);
			if ($object->id == explode("_", $postvar)[3])
				$chbox->setDisabled(true);
            $this->checkboxes[] = $chbox;
		}

		$this->setTitle($object->name);

        $this->fillTemplate();
	}

	/*
	 * Get the $_POST keys of this object´s input
	 *
	 * @return	array		$this->postvars		(column_id => $_POST key in the shape of id_[row_id]_[column_id])
	 */
	public function getPostVars() {
		return $this->postvars;
	}

	/*
	 * Get the object id belonging to this row of the matrix
	 *
	 * @return	integer	    $this->row_id       row id
	 */
	public function getRowId() {
		return $this->row_id;
	}

    /*
     * fill the template with the given checkboxes
	 */
	private function fillTemplate() {
		global $tpl;
		$tpl->addCss('./Customizing/global/plugins/Services/Repository/RepositoryObject/Review/templates/default/css/Review.css');
		$path_to_il_tpl = ilPlugin::getPluginObject(IL_COMP_SERVICE, 'Repository', 'robj', 'Review')->getDirectory();
		$custom_tpl = new ilTemplate("tpl.matrix_row.html", true, true, $path_to_il_tpl);
        foreach ((array) $this->checkboxes as $chbox) {
			$chbox->insert($custom_tpl);
        }

		$this->setHTML($custom_tpl->get());
    }

    /*
	 * set the value of the checkboxes by a given array
	 *
	 * @param 	array		$a_values		associative array of ($postvar => $value)
	 */
	public function setValueByArray($a_value) {
        foreach ((array) $this->checkboxes as $chbox) {
            if (isset($a_value[$chbox->getPostVar()])) {
                $chbox->setChecked(true);
            }
        }
		$this->fillTemplate();
	}
}
?>
