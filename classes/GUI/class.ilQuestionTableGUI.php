<?php

include_once 'Services/Table/classes/class.ilTable2GUI.php';

/*
 * Table GUI for questions
 *
 * @var     string      cmd             command for links to passive GUIs
 */
class ilQuestionTableGUI extends ilTable2GUI {
    private $cmd;

	/*
	 * Constructor, configures GUI output
	 *
	 * @param	object		$parent_obj		    GUI that contains this object
	 * @param	string		$parent_cmd	    	calling command
	 * @param	array		$questions			ilCycleQuestion objects
	 */
    public function __construct(
        $parent_obj,
        $parent_cmd,
        $questions,
        $cmd = ""
    ) {
        global $ilCtrl;

        parent::__construct($parent_obj, $parent_cmd);
        $this->cmd = $cmd;

        $this->addColumn($this->getParentObject()->getTxt("title"), "", "55%");
        $this->addColumn($this->getParentObject()->getTxt("type"), "", "25%");
        $this->addColumn(
            $this->getParentObject()->getTxt("action"),
            "",
            "20%"
        );
        $this->setEnableHeader(true);
        $this->setFormAction($ilCtrl->getFormAction($this->getParentObject()));
        $this->setRowTemplate(
            "tpl.question_table_row.html",
            ilPlugin::getPluginObject(
                IL_COMP_SERVICE,
                'Repository',
                'robj',
                'Review'
            )->getDirectory()
        );
        $this->setDefaultOrderField("id");
        $this->setDefaultOrderDirection("asc");

        $ilCtrl->saveParameterByClass(
            "ilObjReviewGUI",   /* Better don't touch anything with ilCtrl */
            array("q_id", "origin")
        );

        $this->prepareData($questions);
    }

    /*
     * Make associative array from the question objects
     *
     * @param   array       $questions          ilCycleQuestion objects
     */
    private function prepareData($questions) {
        $data = array();
        foreach ($questions as $question) {
            $data[] = array(
                "question_id" => $question->getID(),
                "title" => $question->getTitle(),
                "type" => $question->getQuestionType()
            );
        }
        $this->setData($data);
    }

	/*
	 * Fill a single data row
	 *
	 * @param	array       $row                question data record
	 */
	protected function fillRow($row) {
		global $ilCtrl;

        $ilCtrl->setParameterByClass(
            "ilObjReviewGUI",
            "q_id",
            $row["question_id"]
        );
		$ilCtrl->setParameterByClass("ilObjReviewGUI", "origin", "question");

		$this->tpl->setVariable("TXT_TITLE", $row["title"]);
        $this->tpl->setVariable("TXT_TYPE", $row["type"]);
        $this->tpl->setVariable(
            "TXT_ACTION",
            $this->getParentObject()->getTxt($this->cmd)
        );
        $this->tpl->setVariable(
            "LINK_ACTION",
            $ilCtrl->getLinkTargetByClass("ilObjReviewGUI", $this->cmd)
        );
	}
}
?>
