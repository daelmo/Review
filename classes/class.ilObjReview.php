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

include_once("./Services/Repository/classes/class.ilObjectPlugin.php");
include_once("QuestionManager/class.ilReviewableQuestionPluginGenerator.php");

/*
 * Application class for Review repository object.
 *
 * @var  integer         $group_id               id of the group the plugin object belongs to
 *
 * @author Richard Mörbitz <Richard.Moerbitz@mailbox.tu-dresden.de>
 *
 * $Id$
 */

class ilObjReview extends ilObjectPlugin {
    private $group_id;

    /*
     * Constructor
     */
    public function __construct($a_ref_id = 0) {
        parent::__construct($a_ref_id);
    }


    /*
     * Get type
     */
    final function initType() {
        $this->setType("xrev");
    }

    /*
     * Create object
     */
    function doCreate() {
        global $ilDB, $ilCtrl;

        $ilDB->insert("rep_robj_xrev_revobj",
                array("id" => array("integer", $this->getId()),
                        "group_id" => array("integer", $_GET["ref_id"])
                )
        );
    }

    /*
     * Read data from db
     */
    function doRead() {
        global $ilDB;

        $set = $ilDB->queryF("SELECT * FROM rep_robj_xrev_revobj WHERE id=%s",
                array("integer"),
                array($this->getId())
        );
        while ($rec = $ilDB->fetchAssoc($set)) {
            $this->obj_id = $rec["obj_id"];
            $this->group_id = $rec["group_id"];
        }

        $this->syncQuestionDB();
        $this->generateNewQuestionTypePlugins();
    }

    /*
     * Update data
     */
    function doUpdate() {
            global $ilDB;

            $ilDB->update("rep_robj_xrev_revobj",
                    array("group_id" => array("integer", $this->getGroupId())),
                    array("id" => array("integer", $this->getId()))
            );
    }

    /*
     * Delete data from db
     */
    function doDelete() {
        // pointless, it seems this function is not called by ILIAS
    }

    /*
     * Do Cloning
     */
    function doClone($a_target_id, $a_copy_id, $new_obj) {
        $new_obj->setGroupId($this->getGroupId());
        $new_obj->update();
    }

    /*
     * Get the id of the group this object belongs to
     */
    public function getGroupId() {
        return $this->group_id;
    }

    /*
     * Set the id of the group this object belongs to
     */
    public function setGroupId($group_id) {
        $this->group_id = $group_id;
    }

    /*
     * Max is supposed to document his code
     */
    private function generateNewQuestionTypePlugins() {
        global $ilDB;

        $not_reviewable_types = array();
        $result = $ilDB->query('SELECT type_tag FROM qpl_qst_type WHERE type_tag NOT LIKE "assReviewable%"');
        while ( $data = $ilDB->fetchAssoc( $result ) ) {
            array_push($not_reviewable_types, $data['type_tag']);
        }

        $reviewable_types = array();
        $result = $ilDB->query('SELECT type_tag FROM qpl_qst_type WHERE type_tag LIKE "assReviewable%"');
        while ( $data = $ilDB->fetchAssoc( $result ) ) {
            array_push($reviewable_types, $data['type_tag']);
        }

        foreach ( $not_reviewable_types as $nr_type ) {
            if ( !in_array( 'assReviewable'. substr($nr_type, 3), $reviewable_types ) ) {
                $generator = ilReviewableQuestionPluginGenerator::get();
                $generator->createPlugin( $nr_type );
            }
        }
    }

    /*
     * Load all questions from the groups´ Question Pools,
     * thus updating the plugin´s question db
     */
    private function syncQuestionDB() {
        global $ilDB, $ilUser, $ilPluginAdmin;

        function cmp_rec($a, $b) {
            if ($a["question_id"] > $b["question_id"])
                return 1;
            if ($a["question_id"] < $b["question_id"])
                return -1;
            return 0;
        }

        // uncomment as soos as needed
        // $ilDB->lockTables(array("qpl_questions", "rep_robj_xrev_quest"));
        $qpl = $ilDB->queryF("SELECT qpl_questions.question_id AS question_id, tstamp FROM qpl_questions ".
                "INNER JOIN object_reference ON object_reference.obj_id=qpl_questions.obj_fi ".
                "INNER JOIN crs_items ON crs_items.obj_id=object_reference.ref_id ".
                "INNER JOIN qpl_rev_qst ON qpl_rev_qst.question_id=qpl_questions.question_id ".
                "WHERE crs_items.parent_id=%s AND qpl_questions.original_id IS NULL",
                array("integer"),
                array($this->getGroupId())
        );
        $db_questions = array();
        while ($db_question = $ilDB->fetchAssoc($qpl))
            $db_questions[] = $db_question;
        $pqs = $ilDB->queryF("SELECT * FROM rep_robj_xrev_quest WHERE review_obj=%s",
                array("integer"), array($this->getId())
        );
        $pl_questions = array();
        while ($pl_question = $ilDB->fetchAssoc($pqs))
            $pl_questions[] = $pl_question;

        foreach ($db_questions as $db_question) {
            foreach ($pl_questions as $pl_question) {
                if ($db_question["question_id"] == $pl_question["question_id"]) {
                    if ($db_question["tstamp"] > $pl_question["timestamp"]) {
                        $ilDB->update("rep_robj_xrev_quest",
                                array("timestamp" => array("integer", $db_question["tstamp"])),
                                array("question_id" => array("integer", $db_question["question_id"]),
                                        "review_obj" => array("integer", $this->getId())
                                )
                        );
                        $hist_res = $ilDB->queryF("SELECT * FROM rep_robj_xrev_revi WHERE question_id=%s AND state=%s",
                                array("integer", "integer"), array($db_question["question_id"], 1)
                        );
                        while ($review = $ilDB->fetchAssoc($hist_res)) {
                            $ilDB->insert("rep_robj_xrev_hist", array("timestamp" => array("integer", $review["timestamp"]),
                                            "desc_corr" => array("integer", $review["desc_corr"]),
                                            "desc_relv" => array("integer", $review["desc_relv"]),
                                            "desc_expr" => array("integer", $review["desc_expr"]),
                                            "quest_corr" => array("integer", $review["quest_corr"]),
                                            "quest_relv" => array("integer", $review["quest_relv"]),
                                            "quest_expr" => array("integer", $review["quest_expr"]),
                                            "answ_corr" => array("integer", $review["answ_corr"]),
                                            "answ_relv" => array("integer", $review["answ_relv"]),
                                            "answ_expr" => array("integer", $review["answ_expr"]),
                                            "taxonomy" => array("integer", $review["taxonomy"]),
                                            "knowledge_dimension" => array("integer", $review["knowledge_dimension"]),
                                            "rating" => array("integer", $review["rating"]),
                                            "eval_comment" => array("clob", $review["eval_comment"]),
                                            "expertise" => array("integer", $review["expertise"]),
                                            "question_id" => array("integer", $review["question_id"]),
                                            "id" => array("integer", $review["id"]),
                                            "reviewer" => array("integer", $review["reviewer"])
                                    )
                            );
                        }
                        $ilDB->update("rep_robj_xrev_revi",
                                array("state" => array("integer", 0)),
                                array("question_id" => array("integer", $db_question["question_id"]),
                                        "review_obj" => array("integer", $this->getId())
                                )
                        );
                        $this->notifyReviewersAboutChange($db_question);
                        break;
                    }
                }
            }
        }

        foreach (array_udiff($db_questions, $pl_questions, "cmp_rec") as $new_question) {
            $ilDB->insert("rep_robj_xrev_quest", array("id" => array("integer", $ilDB->nextId("rep_robj_xrev_quest")),
                            "question_id" => array("integer", $new_question["question_id"]),
                            "timestamp" => array("integer", $new_question["tstamp"]),
                            "state" => array("integer", 0),
                            "review_obj" => array("integer", $this->getId())
                    )
            );
            $this->notifyAdminsAboutNewQuestion($new_question);
        }

        foreach (array_udiff($pl_questions, $db_questions, "cmp_rec") as $del_question) {
            $this->notifyReviewersAboutDeletion($del_question);
            $ilDB->manipulateF("DELETE FROM rep_robj_xrev_quest WHERE question_id=%s AND review_obj=%s",
                    array("integer", "integer"),
                    array($del_question["question_id"], $this->getId())
            );
            $ilDB->manipulateF("DELETE FROM rep_robj_xrev_revi WHERE question_id=%s AND review_obj=%s",
                    array("integer", "integer"),
                    array($del_question["question_id"], $this->getId())
            );
        }

        //uncomment as soon as needed
        // $ilDB->unlockTables();
    }

    /*
     * Load all questions in the review cycle that were created by the user in all of the groups´ question pools
     *
     * @return       array           $db_questions           the questions loaded by this function as an associative array
     */
    public function loadQuestionsByUser() {
        global $ilDB, $ilUser;

        $qpl = $ilDB->queryF("SELECT qpl_questions.question_id AS id, title FROM qpl_questions ".
                "INNER JOIN rep_robj_xrev_quest ON rep_robj_xrev_quest.question_id=qpl_questions.question_id ".
                "WHERE qpl_questions.original_id IS NULL AND qpl_questions.owner=%s ".
                "AND rep_robj_xrev_quest.state<%s AND rep_robj_xrev_quest.review_obj=%s",
                array("integer", "integer", "integer"),
                array($ilUser->getId(), 2, $this->getId()));
        $db_questions = array();
        while ($db_question = $ilDB->fetchAssoc($qpl))
            $db_questions[] = $db_question;
        return $db_questions;
    }

    /*
     * Load all reviews created by the user for all questions in the groups´ question pools
     *
     * @return       array           $reviews                the reviews loaded by this function as an associative array
     */
    public function loadReviewsByUser() {
        global $ilDB, $ilUser;

        $rev = $ilDB->queryF("SELECT rep_robj_xrev_revi.id, qpl_questions.title, qpl_questions.question_id, rep_robj_xrev_revi.state FROM rep_robj_xrev_revi ".
                "INNER JOIN qpl_questions ON qpl_questions.question_id=rep_robj_xrev_revi.question_id ".
                "INNER JOIN rep_robj_xrev_quest ON rep_robj_xrev_quest.question_id=rep_robj_xrev_revi.question_id ".
                "WHERE rep_robj_xrev_revi.reviewer=%s AND rep_robj_xrev_revi.review_obj=%s AND rep_robj_xrev_quest.state=1",
                array("integer", "integer"),
                array($ilUser->getId(), $this->getId()));
        $reviews = array();
        while ($review = $ilDB->fetchAssoc($rev))
            $reviews[] = $review;
        return $reviews;
    }

    /*
     * Load a review with a certain ID from the Review Database
     *
     * @param                int             $a_id           ID of the review to load
     *
     * @return       array           $reviews        all reviews with the given ID (exactly one or none)
     */
    public function loadReviewById($a_id) {
        global $ilDB;

        $rev = $ilDB->queryF("SELECT * FROM rep_robj_xrev_revi WHERE id=%s",
                array("integer"),
                array($a_id));

        $reviews = array();
        while ($review = $ilDB->fetchAssoc($rev))
            $reviews[] = $review;
        return $reviews[0];
    }

    /*
     * Update data of an existing review by form input
     *
     * @param                int             $id                     ID of the review to be updated
     * @param                array           $form_data      user input to be stored
     */
    public function storeReviewByID($id, $form_data) {
        global $ilDB;

        $ilDB->update("rep_robj_xrev_revi", array("timestamp" => array("integer", time()),
                "state" => array("integer", 1),
                "desc_corr" => array("integer", $form_data["dc"]),
                "desc_relv" => array("integer", $form_data["dr"]),
                "desc_expr" => array("integer", $form_data["de"]),
                "quest_corr" => array("integer", $form_data["qc"]),
                "quest_relv" => array("integer", $form_data["qr"]),
                "quest_expr" => array("integer", $form_data["qe"]),
                "answ_corr" => array("integer", $form_data["ac"]),
                "answ_relv" => array("integer", $form_data["ar"]),
                "answ_expr" => array("integer", $form_data["ae"]),
                "taxonomy" => array("integer", $form_data["cog_r"]),
                "knowledge_dimension" => array("integer", $form_data["kno_r"]),
                "rating" => array("integer", $form_data["group_e"]),
                "eval_comment" => array("clob", $form_data["comment"]),
                "expertise" => array("integer", $form_data["exp"])),
                array("id" => array("integer", $id)));
    }

    /*
     * Load all review belonging to a question with a certain ID from the Review Database
     *
     * @param                int             $a_id           ID of the question
     *
     * @return       array           $reviews        all reviews with the given ID (exactly one or none)
     */
    public function loadReviewsByQuestion($q_id) {
        global $ilDB;

        $rev = $ilDB->queryF("SELECT * FROM rep_robj_xrev_revi ".
                "WHERE question_id=%s AND review_obj=%s",
                array("integer", "integer"),
                array($q_id, $this->getId()));
        $reviews = array();
        while ($review = $ilDB->fetchAssoc($rev))
            $reviews[] = $review;

        return $reviews;
    }

    /*
     * Load all members of a group
     *
     * @return       array           $members       ids, names of the members
     */
    public function loadMembers() {
        global $ilDB;

        $res = $ilDB->queryF("SELECT usr_data.usr_id AS id, firstname, lastname FROM usr_data ".
                "INNER JOIN rbac_ua ON rbac_ua.usr_id=usr_data.usr_id ".
                "INNER JOIN object_data ON object_data.obj_id=rbac_ua.rol_id ".
                "WHERE object_data.title='il_grp_admin_%s' OR object_data.title='il_grp_member_%s'",
                array("integer", "integer"),
                array($this->getGroupId(), $this->getGroupId()));
        $members = array();
        while ($member = $ilDB->fetchObject($res))
            $members[] = $member;
        return $members;
    }

    /*
     * Load all review cycle phases
     *
     * @return      array           $phases         'phases' table row objects
     */
    public function loadPhases() {
        global $ilDB;

        $res = $ilDB->queryF("SELECT phase, nr_reviewers "
                . "FROM rep_robj_xrev_phases "
                . "WHERE review_obj = %s",
                array("integer"),
                array($this->getId()));

        $phases = array();
        while ($phase = $ilDB->fetchObject($res)) {
            $phases[] = $phase;
        }
        return $phases;
    }

    /*
     * Load all questions that currently have no reviewer allocated to them
     *
     * @return       array           $questions              the question loaded by this function as an associative array
     */
    public function  loadUnallocatedQuestions() {
        global $ilDB, $ilUser;

        $qpl = $ilDB->queryF("SELECT qpl_questions.question_id AS id, title, owner FROM qpl_questions ".
                "INNER JOIN rep_robj_xrev_quest ON rep_robj_xrev_quest.question_id=qpl_questions.question_id ".
                "WHERE qpl_questions.original_id IS NULL AND ".
                "rep_robj_xrev_quest.state=0 AND rep_robj_xrev_quest.review_obj=%s",
                array("integer"),
                array($this->getId()));
        $questions = array();
        while ($question = $ilDB->fetchAssoc($qpl))
            $questions[] = $question;
        return $questions;
    }

    /*
     * Save matrix input as review entities containing the allocated reviewer
     *
     * @param                array           $alloc_matrix           array of arrays of reviewers
     */
    public function allocateReviews($alloc_matrix) {
        global $ilDB;

        $entities = array();
        foreach ($alloc_matrix as $row) {
            foreach ($row["reviewers"] as $reviewer_id => $checked) {
                if (!$checked)
                    continue;
                $ilDB->insert("rep_robj_xrev_revi", array("id" => array("integer", $ilDB->nextID("rep_robj_xrev_revi")),
                        "timestamp" => array("integer", time()),
                        "reviewer" => array("integer", explode("_", $reviewer_id)[2]),
                        "question_id" => array("integer", $row["q_id"]),
                        "state" => array("integer", 0),
                        "desc_corr" => array("integer", 0),
                        "desc_relv" => array("integer", 0),
                        "desc_expr" => array("integer", 0),
                        "quest_corr" => array("integer", 0),
                        "quest_relv" => array("integer", 0),
                        "quest_expr" => array("integer", 0),
                        "answ_corr" => array("integer", 0),
                        "answ_relv" => array("integer", 0),
                        "answ_expr" => array("integer", 0),
                        "taxonomy" => array("integer", 0),
                        "knowledge_dimension" => array("integer", 0),
                        "rating" => array("integer", 0),
                        "eval_comment" => array("clob", ''),
                        "expertise" => array("integer", 0),
                        "review_obj" => array("integer", $this->getId())
                    )
                );
                $ilDB->update("rep_robj_xrev_quest", array("state" => array("integer", 1)),
                        array("question_id" => array("integer", $row["q_id"]), "review_obj" => array("integer", $this->getId())));
            }
        }
    }

    /*
     * Save matrix input as author - reviewer allocation
     *
     * @param       integer     $phase              cycle phase
     * @param       array       $alloc_matrix       black magic
     */
    public function allocateReviewers($phase, $alloc_matrix) {
        global $ilDB;

        $ilDB->manipulateF("DELETE FROM rep_robj_xrev_alloc " .
                    "WHERE phase=%s AND review_obj=%s",
                    array("integer", "integer"),
                    array($phase, $this->getId()));

        foreach ($alloc_matrix as $row) {
            foreach ($row["reviewers"] as $reviewer_id => $checked) {
                if (!$checked) {
                    continue;
                }
                $ilDB->insert("rep_robj_xrev_alloc", array(
                        "phase" => array("integer", $phase),
                        "reviewer" => array("integer", explode("_", $reviewer_id)[2]),
                        "author" => array("integer", $row["q_id"]),
                        "review_obj" => array("integer", $this->getId())));
            }
        }
    }

    /*
     * Remove questions from the review cycle by marking them as finished
     *
     * @param                array           $questions              array of question_ids
     */
    public function finishQuestions($questions) {
        global $ilDB;
        foreach ($questions as $question_id) {
            $ilDB->update("rep_robj_xrev_quest",
                    array("state" => array("integer", 2)),
                    array("question_id" => array("integer", $question_id),
                            "review_obj" => array("integer", $this->getId())
                    )
            );
        }
    }

    /*
     * Load metadata of a question
     *
     * @param                int             $q_id                   question id
     *
     * @return       array           $question       $question metadata as an associative array
     */
    public function loadQuestionMetaData($q_id) {
        global $ilDB;
        $req = $ilDB->queryF("SELECT qpl_questions.title, qpl_questions.description, usr_data.firstname, usr_data.lastname ".
                "FROM qpl_questions ".
                "INNER JOIN usr_data ON usr_data.usr_id=qpl_questions.owner ".
                "WHERE qpl_questions.question_id=%s",
                array("integer"),
                array($q_id)
        );
        return $ilDB->fetchAssoc($req);
    }

    /*
     * Load taxonomy and knowledge dimension of a question
     *
     * @param                int             $q_id                   question id
     *
     * @return       array           $question       $question taxonomy data as an associative array
     */
    public function loadQuestionTaxonomyData($q_id) {
        global $ilDB, $ilPluginAdmin;
        if (!$ilPluginAdmin->isActive(IL_COMP_MODULE, "TestQuestionPool", "qst", "assReviewableMultipleChoice"))
            return array();
        $req = $ilDB->queryF("SELECT qpl_rev_qst.taxonomy, qpl_rev_qst.knowledge_dimension ".
                "FROM qpl_rev_qst ".
                "WHERE qpl_rev_qst.question_id=%s",
                array("integer"),
                array($q_id)
        );
        return $ilDB->fetchAssoc($req);
    }

    /*
     * Prepare message output to inform reviewers about
     * their allocation to a certain question
     *
     * @param                array                   $alloc_matrix                   array of arrays of reviewers
     */
    public function notifyReviewersAboutAllocation($alloc_matrix) {
        $receivers = array();
        foreach ($alloc_matrix as $row)
            foreach ($row["reviewers"] as $reviewer_id => $checked)
                if ($checked)
                    $receivers[] = explode("_", $reviewer_id)[2];
        $this->performNotification($receivers, "msg_review_requested");
    }

    /*
     * Prepare message output to inform authors about
     * the acceptance of a certain question by the group´s admin
     *
     * @param                array                   $question_ids                   array of the ids of the accepted question
     */
    public function notifyAuthorsAboutAcceptance($question_ids) {
        global $ilDB;
        $receivers = array();
        foreach ($question_ids as $id)
            $receivers[] = $ilDB->fetchAssoc($ilDB->queryF("SELECT owner FROM qpl_questions WHERE question_id=%s",
                    array("integer"), array($id)))["owner"];
        $this->performNotification($receivers, "msg_question_accepted");
    }

    /*
     * Prepare message output to inform an author about
     * the completion of a review on a certain question
     *
     * @param                integer                 $review_id                      id of the completed review
     */
    public function notifyAuthorAboutCompletion($review_id) {
        global $ilDB;
        $rev = $ilDB->queryF("SELECT owner FROM qpl_questions ".
                "INNER JOIN rep_robj_xrev_revi ON rep_robj_xrev_revi.question_id=qpl_questions.question_id ".
                "WHERE rep_robj_xrev_revi.id=%s",
                array("integer"),
                array($review_id)
        );
        $receivers = array();
        while ($receiver = $ilDB->fetchAssoc($rev))
            $receivers[] = $receiver["owner"];
        $this->performNotification($receivers, "msg_review_completed");
    }

    /*
     * Prepare message output to inform reviewers about
     * a change of a certain question they have to review
     *
     * @param                array                   $question                       question data as an associative array
     */
    public function notifyReviewersAboutChange($question) {
        global $ilDB;
        $res = $ilDB->queryF("SELECT reviewer FROM rep_robj_xrev_revi ".
                "WHERE review_obj=%s AND question_id=%s",
                array("integer", "integer"),
                array($this->getId(), $question["question_id"])
        );
        $receivers = array();
        while ($receiver = $ilDB->fetchAssoc($res))
            $receivers[] = $receiver["reviewer"];
        $this->performNotification($receivers, "msg_question_edited");
    }

    /*
     * Prepare message output to inform the group´s admins about
     * the creation of a new question
     *
     * @param                array                   $question                       question data as an associative array
     */
    public function notifyAdminsAboutNewQuestion($question) {
        global $ilDB;
        $res = $ilDB->queryF("SELECT usr_id FROM rbac_ua ".
                "INNER JOIN object_data ON object_data.obj_id=rbac_ua.rol_id ".
                "WHERE object_data.title='il_grp_admin_%s'",
                array("integer"),
                array($this->getGroupId())
        );
        $receivers = array();
        while ($receiver = $ilDB->fetchAssoc($res))
            $receivers[] = $receiver["usr_id"];
        $this->performNotification($receivers, "msg_question_created");
    }

    /*
     * Prepare message output to inform reviewers about
     * the deletion of a question they had to review
     *
     * @param                array                   $question                       question data as an associative array
     */
    public function notifyReviewersAboutDeletion($question) {
        global $ilDB;
        $res = $ilDB->queryF("SELECT reviewer FROM rep_robj_xrev_revi ".
                "WHERE review_obj=%s AND question_id=%s",
                array("integer", "integer"),
                array($this->getId(), $question["question_id"])
        );
        $receivers = array();
        while ($receiver = $ilDB->fetchAssoc($res))
            $receivers[] = $receiver["reviewer"];
        $this->performNotification($receivers, "msg_question_deleted");
    }

    /*
     * Created and send an ILIAS message based on data prepared by this object´s notify... methods
     *
     * @param                array                   $receivers                      array of user ids corresponding to the receivers of the message
     * @param                string          $message_type           the kind of information to be sent
     */
    private function performNotification($receivers, $message_type) {
        include_once "./Services/Notification/classes/class.ilSystemNotification.php";
        $ntf = new ilSystemNotification();
        $ntf->setObjId($this->getId());
        $ntf->setLangModules(array("rep_robj_xrev"));
        $ntf->setSubjectLangId("rep_robj_xrev_".$message_type."_subj");
        $ntf->setIntroductionLangId("rep_robj_xrev_".$message_type."_intr");
        $ntf->setGotoLangId("rep_robj_xrev_obj_xrev");

        $ntf->sendMail($receivers);
    }

    /*
     * Load all questions that are not reviewable
     *
     * @return   array       $questions      associative array of question data
     */
    public function loadNonReviewableQuestions() {
        global $ilDB;

        $res = $ilDB->queryF("SELECT question_id, type_tag, title, author FROM qpl_questions " .
                             "INNER JOIN object_reference ON object_reference.obj_id=qpl_questions.obj_fi ".
                                                         "INNER JOIN crs_items ON crs_items.obj_id=object_reference.ref_id ".
                             "INNER JOIN qpl_qst_type ON qpl_qst_type.question_type_id=qpl_questions.question_type_fi " .
                             "WHERE crs_items.parent_id=%s",
                             array("integer"),
                             array($this->getGroupId())
               );

        $questions = array();
        while ($question = $ilDB->fetchAssoc($res))
            $questions[] = $question;
        foreach ($questions as $index => $question) {
            if (strpos($question["type_tag"], "assReviewable") !== FALSE)
                unset($questions[$index]);
        }
        return $questions;
    }

    /*
     * Update a former non reviewable question
     *
     * @param   int         $id         id of the question to update
     * @param   int         $tax        taxonomy -""-
     * @param   int         $knowd      knowledge dimension -""-
     */
    public function saveQuestionConversion($id, $tax, $knowd) {
        global $ilDB;

        $res = $ilDB->queryF("SELECT type_tag FROM qpl_qst_type " .
                             "INNER JOIN qpl_questions ON qpl_questions.question_type_fi=qpl_qst_type.question_type_id " .
                             "WHERE question_id=%s",
                             array("integer"),
                             array($id)
               );
        $old_type = $ilDB->fetchAssoc($res)["type_tag"];
        $new_type = sprintf("assReviewable%s", substr($old_type, 3));
        $res = $ilDB->queryF("SELECT question_type_id FROM qpl_qst_type " .
                             "WHERE type_tag=%s",
                             array("text"),
                             array($new_type)
               );
        $type_id = $ilDB->fetchAssoc($res)["question_type_id"];
        $ilDB->update("qpl_questions",
            array("question_type_fi" => array("integer", $type_id)),
            array("question_id" => array("integer", $id))
        );
        $ilDB->insert("qpl_rev_qst",
            array("question_id" => array("integer", $id),
                "taxonomy" => array("integer", $tax),
                "knowledge_dimension" => array("integer", $knowd)
            )
        );
    }

    /*
     * Get a review plugin specific enumeration
     *
     * @param       string      $identifier     name of the enumeration
     *
     * @return      array       $taxonomies     associative array of enum entry id => term
     */
    public static function getEnum($identifier) {
        global $ilDB, $lng;
        switch ($identifier) {
        case "taxonomy": $table = "taxon"; break;
        case "knowledge dimension": $table = "knowd"; break;
        case "evaluation": $table = "eval"; break;
        case "rating": $table = "rate"; break;
        case "expertise": $table = "expert"; break;
        /*
        case "learning outcome": $table = "loutc"; break;
        case "content": $table = "cont"; break;
        case "topic": $table = "topic"; break;
        case "subject area": $table = "subar"; break
        */
        default: return null;
        }
        $res = $ilDB->query("SELECT * FROM rep_robj_xrev_$table");
        $enum = array();
        while ($entry = $ilDB->fetchAssoc($res))
            $enum[$entry["id"]] = $lng->txt("rep_robj_xrev_".$entry["term"]);
        return $enum;
    }

    public function addPhaseToCycle() {
        global $ilDB;

        $res = $ilDB->queryF("SELECT MAX(phase) AS maxphase "
                . "FROM rep_robj_xrev_phases "
                . "WHERE review_obj=%s",
                array("integer"),
                array($this->getID()));

        $maxphase = $ilDB->fetchAssoc($res)["maxphase"];
        $ilDB->insert("rep_robj_xrev_phases",
            array("phase" => array("integer", $maxphase + 1),
                "review_obj" => array("integer", $this->getID()),
                "nr_reviewers" => array("integer", 0)));
    }

    public function removePhaseFromCycle() {
        global $ilDB;

        $res = $ilDB->queryF("SELECT MAX(phase) AS maxphase "
                . "FROM rep_robj_xrev_phases "
                . "WHERE review_obj=%s",
                array("integer"),
                array($this->getID()));

        $maxphase = $ilDB->fetchAssoc($res)["maxphase"];
        $ilDB->manipulateF("DELETE FROM rep_robj_xrev_phases "
                . "WHERE phase=%s AND review_obj=%s",
                array("integer", "integer"),
                array($maxphase, $this->getID()));

        $ilDB->manipulateF("DELETE FROM rep_robj_xrev_alloc "
                . "WHERE phase=%s AND review_obj=%s",
                array("integer", "integer"),
                array($maxphase, $this->getID()));
    }
}
?>
