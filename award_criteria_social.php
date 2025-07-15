<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the activity badge award criteria type class
 *
 * @package    core
 * @subpackage badges
 * @copyright  2024 Matthew Faulkner <matthewfaulkner@apoaevents.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


//define social criteria types.
define('SOCIAL_TYPE_SINGLE_POST_LIKES', 1);
define('SOCIAL_TYPE_TOTAL_LIKES', 2);
define('SOCIAL_TYPE_TOTAL_POSTS', 3);
define('SOCIAL_TYPE_TOTAL_REPLIES', 4);

/**
 * 
 * Award Criteria for social participation
 *
 * @copyright  2024 Matthew Faulkner <matthewfaulkner@apoaevents.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class award_criteria_social extends award_criteria {

    /**
     * Social participation criteria type for this criteria
    */
    public $criteriatype = BADGE_CRITERIA_TYPE_SOCIAL;

    /**
     * Undocumented variable
     *
     * @var integer courseid
     */
    private int $courseid;

    /**
     * Course Object
     *
     * @var stdClass $course
     */
    private stdClass $course;

    /**
     * Required parameters
     *
     * @var string $required_param
     */
    public $required_param = 'module';

    /**
     * Optional parameters for criteria
     *
     * @var array
     */
    public $optional_params = array('bydate', 'socialtype', 'socialvalue', 'forum', 'allforums', 'refresh');

    /**
     * Types of social participation criteria
     *
     * @var array
     */
    private array $types = array(
        SOCIAL_TYPE_SINGLE_POST_LIKES => "singlepostlikes",
        SOCIAL_TYPE_TOTAL_LIKES => "totallikes",
        SOCIAL_TYPE_TOTAL_POSTS => "totalposts",
        SOCIAL_TYPE_TOTAL_REPLIES => "totalreplies",
    );

    /**
     * Criteria constructor
     *
     * @param array $record
     */
    public function __construct($record) {
        global $DB;
        parent::__construct($record);

        $this->course = $DB->get_record_sql('SELECT c.id, c.enablecompletion, c.cacherev, c.startdate
                        FROM {badge} b LEFT JOIN {course} c ON b.courseid = c.id
                        WHERE b.id = :badgeid ', array('badgeid' => $this->badgeid), MUST_EXIST);

        // If the course doesn't exist but we're sure the badge does (thanks to the LEFT JOIN), then use the site as the course.
        if (empty($this->course->id)) {
            $this->course = get_course(SITEID);
        }
        $this->courseid = $this->course->id;
    }

    /**
     * Gets the forum instance from the database and returns it.
     * If no forum instance exists this function returns false.
     * 
     * @param int $forumid id of forum
     * 
     * @return mixed a fieldset object containing the first matching record, false or exception if error not found depending on mode
     */
    private function get_forum_instance($forumid) {
        global $DB;
        $rec = $DB->get_record_sql("SELECT f.name
                               FROM {forum} f
                               WHERE f.id = ? ", array($forumid));

        return $rec;
    }

    /**
     * Get criteria description for displaying to users
     *
     * @param string $short 
     * 
     * @return string
     */
    public function get_details($short = '') {
        global $OUTPUT;
        $output = array();
        foreach ($this->params as $p) {
            $str = "";
            if($p['allforums'] == 1){
                $forums = $this->get_forums_in_course($this->course->id);
                $forumouts = [];
                foreach($forums as $forum){
                    $forumouts[] = html_writer::tag('b', '"' . get_string('modulename', 'forum') . ' - ' . $forum->name . '"');
                }
                $str .=  html_writer::alist($forumouts, array(), 'ul');
            }
            else{
                $mod = self::get_forum_instance($p['forum']);
                if (!$mod) {
                    $str .= $OUTPUT->error_text(get_string('error:nosuchmod', 'badges'));
                } else {
                    $str .= html_writer::tag('b', '"' . get_string('modulename', 'forum') . ' - ' . $mod->name . '"');
                    
                }
            }
            $str .= html_writer::tag('p', get_string($this->types[$p['socialtype']], 'badges', $p['socialvalue']));
                if (isset($p['bydate'])) {
                    $str .= get_string('criteria_descr_bydate', 'badges', userdate($p['bydate'], get_string('strftimedate', 'core_langconfig')));
                }
            $output[] = $str;
        }

        if ($short) {
            return implode(', ', $output);
        } else {
            return html_writer::alist($output, array(), 'ul');
        }
    }

    /**
     * Add appropriate new criteria options to the form
     *
     * @return array ($none, $error_string) $none = true indicates no options
     */
    public function get_options(&$mform) {
        $none = true;
        $missing = false;

        $course = $this->course;
        $mods = $this->get_forums_in_course($course->id);
        
        if ($this->id !== 0) {
            $missing = !array_key_exists($this->params[1]['forum'], $mods);
            $missing = !isset($this->params[1]['allforums']);
        }

        if ($missing) {
            $mform->addElement('header', 'category_errors', get_string('criterror', 'badges'));
            $mform->addHelpButton('category_errors', 'criterror', 'badges');
            $this->config_options($mform, array('id' => 1, 'checked' => true,
                    'name' => get_string('error:nosuchmod', 'badges'), 'error' => true));
            $none = false;
            
        }

        if (!empty($mods)) {
            $mform->addElement('header', 'first_header', $this->get_title());
            $forumoptions = [];


            foreach ($mods as $mod) {
                $forumoptions[$mod->id] = $mod->name;
            }

            $mod = reset($mods);

            if(empty($forumoptions)){
                $mform->addElement('header', 'category_errors', get_string('criterror', 'badges'));
                $mform->addHelpButton('category_errors', 'criterror', 'badges');
                $none = false;
                return array($none, get_string('error:noactivities', 'badges'));
            }

            $param = array(
                    'id' => $this->id,
                    'name' => get_string('modulename', $mod->modname) . ' - ' . $mod->name,
                    'error' => false,
                    'checked' => true,
                    'forumoptions' => $forumoptions,
                    'socialtype' => SOCIAL_TYPE_SINGLE_POST_LIKES
                    );
            
            
            if ($this->id !== 0) {
                if(isset($this->params[1]['allforums'])) {
                    $param['allforums'] = $this->params[1]['allforums'];
                }
                if(isset($this->params[1]['forum'])) {
                    $param['forum'] = $this->params[1]['forum'];
                }
                if(isset($this->params[1]['socialvalue'])) {
                    $param['socialvalue'] = $this->params[1]['socialvalue'];
                }
                if(isset($this->params[1]['socialtype'])) {
                    $param['socialtype'] = $this->params[1]['socialtype'];
                }
                if(isset($this->params[1]['bydate'])) {
                    $param['bydate'] = $this->params[1]['bydate'];
                }
                if(isset($this->params[1]['refresh'])) {
                    $param['refresh'] = $this->params[1]['refresh'];
                }
            }
            $this->config_options($mform, $param);
            $none = false;
            
        }


        return array($none, get_string('error:noforums', 'badges'));
    }

    /**
     * Review this criteria and decide if it has been completed
     *
     * @param int $userid User whose criteria completion needs to be reviewed.
     * @param bool $filtered An additional parameter indicating that user list
     *        has been reduced and some expensive checks can be skipped.
     *
     * @return bool Whether criteria is complete
     */
    public function review($userid, $filtered = false) {
        global $DB;

        //course hasnt started yet
        if ($this->course->startdate > time()) {
            return false;
        }
        $param = $this->params[1];

        if($param['allforums'] == 1){
            $forums = $this->get_forums_in_course($this->course->id);
            if(empty($forums)){
                return false;
            }
            list($forumwheres, $forumparams) = $DB->get_in_or_equal(array_keys($forums), SQL_PARAMS_NAMED);
        }else{
            $forumwheres = ' = :forumid';
            $forumparams = ['forumid' => $param['forum']];
        }
        
        $overall = false;

        $selects = "SELECT p.id, d.forum";
        $joins = "FROM {forum_discussions} d INNER JOIN 
                {forum_posts} p ON d.id = p.discussion";
        $wheres = "WHERE
                p.userid = :userid 
                AND r.component = :component 
                AND r.ratingarea = :post
                AND d.forum $forumwheres";
        $groupby = "";
        $having = "";
        $sqlparams = [
            'userid' => $userid,
            'component' => 'mod_forum',
            'post' => 'post',
            'socialvalue' => $param['socialvalue']];

        if($param['refresh'] == 1){
            $joins .=  " LEFT JOIN {badge_issued} bi ON p.userid = bi.userid AND bi.badgeid = :badgeid ";
            $wheres .= " AND (bi.dateissued IS NULL OR (p.modified > bi.dateissued AND r.timemodified > bi.dateissued)) ";
            $sqlparams['badgeid'] = $this->badgeid;
        }


        //add date filters
        if (isset($param['bydate'])) {
            $wheres .= " AND p.modified > :bydate AND r.timemodified > :bydater";
            $sqlparams["bydate"] = $param['bydate'];
            $sqlparams["bydater"] = $param['bydate'];
        }

        //add social type specific sql
        switch ($param['socialtype']) {
            case SOCIAL_TYPE_SINGLE_POST_LIKES:
                $joins .= "  JOIN {rating} r ON p.id = r.itemid ";
                $groupby .= "GROUP BY p.id";
                $having = "HAVING COUNT(r.id) >= :socialvalue";
                break;
            case SOCIAL_TYPE_TOTAL_LIKES:
                $joins .= "  JOIN {rating} r ON p.id = r.itemid";
                $selects .= ", COUNT(r.id) as ratingcount";
                $groupby .= "GROUP BY d.forum";
                $having .= "HAVING count(r.id) >= :socialvalue";
                break;
            case SOCIAL_TYPE_TOTAL_POSTS:
                $joins .= "  JOIN {rating} r ON p.id = r.itemid";
                $groupby .= "GROUP BY d.forum";                  
                $having = "HAVING COUNT(DISTINCT p.id) > :socialvalue";
                break;
            case SOCIAL_TYPE_TOTAL_REPLIES:
                $joins .= "  JOIN {rating} r ON p.id = r.itemid";
                $wheres .= " AND p.id IN (
                                        SELECT parent FROM {forum_posts}
                                        WHERE userid <> :parentuid
                                        GROUP BY parent
                                        HAVING COUNT(DISTINCT(userid)) > :socialvalue) ";

                $sqlparams['parentuid'] = $userid;
                break;
        };
            
    
        $fullquery = "$selects
                    $joins
                    $wheres
                    $groupby
                    $having";

        $result = $DB->get_records_sql($fullquery, array_merge($sqlparams, $forumparams));

        //any results return true.
        if(!empty($result)){
            $overall = true;
        }
        

        return $overall;
    }

    /**
     * Returns array with sql code and parameters returning all ids
     * of users who meet this particular criterion.
     *
     * @return array list($join, $where, $params)
     */
    public function get_completed_criteria_sql() {
        global $DB;
        $join = '';
        $where = '';
        $params = array();
        
        $param = $this->params[1];
         if($param['allforums'] == 1){
            $forums = $this->get_forums_in_course($this->course->id);
            if(empty($forums)){
                return array('', '', '');
            }
            list($forumwheres, $forumparams) = $DB->get_in_or_equal(array_keys($forums), SQL_PARAMS_NAMED);
        }else{
            $forumwheres = ' = :forumid';
            $forumparams = ['forumid' => $param['forum']];
        }



        $sql = "SELECT DISTINCT p.userid 
                FROM {forum_posts} p 
                INNER JOIN {forum_discussions} d ON p.discussion = d.id
                WHERE d.forum $forumwheres";

        $userids = $DB->get_records_sql($sql, $forumparams);

        $selects = "SELECT p.id, d.forum";
        $joins = "FROM {forum_discussions} d INNER JOIN 
                {forum_posts} p ON d.id = p.discussion";
        $wheres = "WHERE
                p.userid = :userid AND
                r.component = :component AND r.ratingarea = :post
                AND d.forum $forumwheres";
        $groupby = "";
        $having = "";
        $sqlparams = [
            'component' => 'mod_forum',
            'post' => 'post',
            'socialvalue' => $param['socialvalue']];
        $sqlparams = array_merge($sqlparams, $forumparams);

        if($param['refresh'] == 1){
            $joins .=  " LEFT JOIN {badge_issued} bi ON p.userid = bi.userid AND bi.badgeid = :badgeid ";
            $wheres .= " AND (bi.dateissued IS NULL OR (p.modified > bi.dateissued AND r.timemodified > bi.dateissued)) ";
            $sqlparams['badgeid'] = $this->badgeid;
        }


        if (isset($param['bydate'])) {
            $wheres .= " AND p.modified < :bydate AND r.timemodified < :bydate";
            $sqlparams["bydate"] = $param['bydate'];
            $sqlparams["bydater"] = $param['bydate'];
        }
        

        switch ($param['socialtype']) {
            case SOCIAL_TYPE_SINGLE_POST_LIKES:
                $joins .= " JOIN {rating} r ON p.id = r.itemid";
                $groupby .= "GROUP BY p.id";
                $having = "HAVING COUNT(r.id) >= :socialvalue";
                break;
            case SOCIAL_TYPE_TOTAL_LIKES:
                $joins .= "  JOIN {rating} r ON p.id = r.itemid";
                $selects .= ", COUNT(r.id) as ratingcount";
                $groupby .= "GROUP BY d.forum";
                $having .= "HAVING count(r.id) >= :socialvalue";
                break;
            case SOCIAL_TYPE_TOTAL_POSTS:
                $joins .= "  JOIN {rating} r ON p.id = r.itemid";
                $groupby .= "GROUP BY d.forum";                  
                $having = "HAVING COUNT(DISTINCT p.id) > :socialvalue";
                break;
            case SOCIAL_TYPE_TOTAL_REPLIES:
                $joins .= "  JOIN {rating} r ON p.id = r.itemid";
                $wheres .= " AND p.id IN (
                                        SELECT parent FROM {forum_posts}
                                        WHERE userid <> :parentuid
                                        GROUP BY parent
                                        HAVING COUNT(id) > :socialvalue) ";
                break;
        };
            
    
        $fullquery = "$selects
                    $joins
                    $wheres
                    $groupby
                    $having";
        
        $useridsbadgeable = array_keys(array_filter(
            $userids,
            function ($user) use ($fullquery, $sqlparams) {
                global $DB;
                $params = array_merge($sqlparams, ['userid' => $user->userid, 'parentuid' => $user->userid]);
                $result = $DB->get_records_sql($fullquery, $params);
                return !empty($result);
            }
        ));

        // Finally create a where statement (if neccessary) with all userids who are allowed to get the badge.
        // This list also includes all users who have previously received the badge. These are filtered out in the badge.php.
        $join = "";
        $where = "";
        if (!empty($useridsbadgeable)) {
            list($wherepart, $params) = $DB->get_in_or_equal($useridsbadgeable, SQL_PARAMS_NAMED);
            $where = " OR u.id " . $wherepart;
        }
        return array($join, $where, $params);
        
    }

        /**
     * Add appropriate parameter elements to the criteria form
     *
     */
    public function config_options(&$mform, $param) {
        global $OUTPUT;
        $prefix = $this->required_param . '_';
        
        if ($param['error']) {
            $parameter[] =& $mform->createElement('advcheckbox', $prefix . '1', '',
                    $OUTPUT->error_text($param['name']), null, array(0, 1));
            $mform->addGroup($parameter, 'param_' . $prefix . '1', '', array(' '), false);
        } else {
            $parameter[] =& $mform->createElement('hidden', $prefix . '1', 1);
            $parameter[] =& $mform->createElement('static', 'break_start_' . $param['id'], null,
                '<div class="ml-3 mt-1 w-100 align-items-center">');

            $parameter[] =& $mform->createElement('checkbox', 'allforums_1', get_string('allforums', 'badges'));
            $parameter[] =& $mform->createElement('select', 'forum_1', get_string('forum'), $param['forumoptions']);

            $parameter[] =& $mform->createElement('select', 'socialtype_1', get_string('types'), $this->types);
            
            $parameter[] =& $mform->createElement('text', 'socialvalue_1', get_string('socialvalue'));
            $mform->setType('socialvalue_1', PARAM_INT);
            
            if (in_array('bydate', $this->optional_params)) {
                $parameter[] =& $mform->createElement('static', 'complby_1', null, get_string('bydate', 'badges'));
                $parameter[] =& $mform->createElement('date_selector', 'bydate_1', "", array('optional' => true));
            }

            $parameter[] =& $mform->createElement('advcheckbox', 'refresh_1', get_string('refresh', 'badges'), get_string('refresh_help', 'badges'));
            
            
            $parameter[] =& $mform->createElement('static', 'break_end_1', null, '</div>');
            $mform->addGroup($parameter, 'param_' . $prefix . '1', get_string('socialbadgecriteria', 'badges'), array(' '), false);
            
            $mform->addHelpButton('param_' . $prefix . '1', 'socialbadgecriteria', 'badges');
            $mform->addGroupRule('param_' . $prefix . '1', array(
                    'socialvalue_1' => array(array(get_string('err_numeric', 'form'), 'required', '', 'client'))));
            
            $mform->hideIf('forum_1', 'allforums_1', 'checked');
            $mform->disabledIf('bydate_1' . '[day]', 'bydate_1' . '[enabled]', 'notchecked');
            $mform->disabledIf('bydate_1' . '[month]', 'bydate_1' . '[enabled]', 'notchecked');
            $mform->disabledIf('bydate_1' . '[year]', 'bydate_1' . '[enabled]', 'notchecked');

            $mform->setDefault('socialtype_1', $param['socialtype']);
            $mform->setDefault('allforums_1', $param['allforums']);
            $mform->setDefault('refresh_1', $param['refresh']);
        }

        // Set default values.
        $mform->setDefault($prefix . '1', 1);
        
        if (isset($param['forum'])) {
            $mform->setDefault('forum_1', $param['forum']);
        }
        if (isset($param['socialtype'])) {
            $mform->setDefault('socialtype_1', $param['socialtype']);
        }
        if (isset($param['socialvalue'])) {
            $mform->setDefault('socialvalue_1', $param['socialvalue']);
        }
        if (isset($param['bydate'])) {
            $mform->setDefault('bydate_1', $param['bydate']);
        }
        if (isset($param['refresh'])) {
            $mform->setDefault('refresh_1', $param['refresh']);
        }
    }



        /**
     * Returns all forums with rating enabled in course
     *
     * @param string $modulename The module name (forum, quiz, etc.)
     * @param int $courseid The course id to get modules for
     * @param string $extrafields extra fields starting with m.
     * @return array Array of results
     */
    function get_forums_in_course($courseid) {
        global $DB;

        if (!core_component::is_valid_plugin_name('mod', 'forum')) {
            throw new coding_exception('Invalid modulename parameter');
        }
        $params = array();
        $params['courseid'] = $courseid;

        return $DB->get_records_sql("SELECT m.*
                                    FROM {forum} m
                                    WHERE  m.course = :courseid AND
                                            (m.assessed = 2 OR m.assessed = 5)", $params);
    }
}
