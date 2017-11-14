<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage core
 */

/**
 * This class incapsulates operation with scheduling
 *
 * It operates on DB row objects by changing it's fields. After applying changes on object, this
 * object should be saved in DB by $DB->insert_record or $DB->update_record
 *
 * To avoid overwriting other fields use scheduler::to_object().
 * This method will return object with only scheduler specific fields and 'id' field
 * Scheduler changes original object fields aswell, so no need to use scheduler::to_object() if you
 * save original object after applying scheduler changes.
 *
 * To support scheduling db table represented by operated db row object must have next fields:
 * frequency (int), schedule(int), nextevent (bigint)
 * If field(s) have dfferent names it can be configured via set_field method
 * Also, it has tight integration with Scheduler form element, and as result it's easily to integrate
 * them.
 */

namespace tool_etl;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/calendar/lib.php');

class scheduler {
    /**
     *  Schedule constants
     *
     */
    const DAILY = 1;
    const WEEKLY = 2;
    const MONTHLY = 3;
    const HOURLY = 4;
    const MINUTELY = 5;

    /**
     * DB row decorated object
     *
     * @var \stdClass
     */
    protected $subject = null;

    /**
     * status changes
     *
     * @var bool
     */
    protected $changed = false;

    /**
     * Mapping of field names used by scheduler
     *
     * @var array
     */
    protected $map = array('frequency' => 'frequency',
                           'schedule' => 'schedule',
                           'nextevent' => 'nextevent',
                           // Note timezone is not stored, it has to be specified as parameter of next() instead.
                          );

    protected $time = 0;
    /**
     * Constructor
     *
     * @param stdClass DB row object
     * @param array $alias_map Optional field renaming
     */
    public function __construct(\stdClass $row = null, array $alias_map = array()) {
        if (is_null($row)) {
            $row = new \stdClass();
        }
        $this->subject = $row;
        // Remap and add fields.
        foreach ($this->map as $k => $v) {
            $v = (isset($alias_map[$k])) ? $alias_map[$k] : $v;
            $this->set_field($k, $v);
            $this->subject->{$v} = isset($this->subject->{$v}) ? $this->subject->{$v} : null;
        }
        $this->set_time();
    }

    /**
     * Set operational time
     *
     * @param int $time
     */
    public function set_time($time = null) {
        if (is_null($time)) {
            $this->time = time();
        } else {
            $this->time = $time;
        }
    }

    /**
     * Change field name used by scheduler to filed represented in db row object
     *
     * @param string $name Name used in scheduler
     * @param string $alias Field used in DB
     */
    public function set_field($name, $alias) {
        if (isset($this->map[$name])) {
            $this->map[$name] = $alias;
        }
    }

    public function do_asap() {
        $this->changed = true;
        $this->subject->{$this->map['nextevent']} = $this->time - 1;
    }

    /**
     * Calculate next time of execution
     *
     * @param int $timestamp Current date, specify value in unit tests only
     * @param bool $is_cron False means we are saving new value, true means do not schedule for today for weekly and monthly frequency.
     * @param string $forcetimezone may be used to override current user timezone, for example for system timezone scheduling
     * @return scheduler $this
     */
    public function next($timestamp = null, $is_cron = true, $forcetimezone = null) {
        if (!isset($this->subject->{$this->map['frequency']})) {
            return $this;
        }

        $this->set_time($timestamp);

        $this->changed = true;
        $frequency = $this->subject->{$this->map['frequency']};
        $schedule = $this->subject->{$this->map['schedule']};

        $next = new \DateTime('@' . $this->time);
        $next->setTimezone(\core_date::get_user_timezone_object($forcetimezone));

        switch ($frequency) {
            case self::MINUTELY:
                $timeminute = $next->format('i');
                $timehour = $next->format('H');
                $timeminute = (int)(floor($timeminute / $schedule) * $schedule);
                $next->setTime($timehour, $timeminute, 0);
                if ($next->getTimestamp() <= $this->time) {
                    $next->add(new \DateInterval('PT' . $schedule . 'M'));
                }
                break;
            case self::HOURLY:
                $timehour = $next->format('H');
                $timehour = (int)(floor($timehour / $schedule) * $schedule);
                $next->setTime($timehour, 0, 0);
                if ($next->getTimestamp() <= $this->time) {
                    $next->add(new \DateInterval('PT' . $schedule . 'H'));
                }
                break;
            case self::DAILY:
                $next->setTime($schedule, 0, 0);
                if ($next->getTimestamp() <= $this->time) {
                    $next->add(new \DateInterval('P1D'));
                }
                break;
            case self::WEEKLY:
                $timeweekday = $next->format('w'); // Current day of week, Sunday is 0.
                if (($schedule == $timeweekday) && (!$is_cron)) {
                    // The scheduled day of the week is the same as the given day of the week, so schedule for this day.
                    $next->setTime(0, 0, 0);
                } else {
                    $diff = $schedule - $timeweekday;
                    if ($diff <= 0) {
                        $diff = $diff + 7;
                    }
                    $next->setTime(0, 0, 0);
                    $next->add(new \DateInterval('P' . $diff . 'D'));
                }
                break;
            case self::MONTHLY:
                $timeday = $next->format('j');
                if (($timeday == $schedule) && (!$is_cron)) {
                    // The scheduled day of the month is the same as the given day of the week, so schedule for this day.
                    $next->setTime(0, 0, 0);
                } else {
                    $next->setTime(0, 0, 0);
                    $timemonth = (int)$next->format('n');
                    $timeyear = $next->format('Y');
                    $maxdays = (int)$next->format('t');
                    if ($maxdays < $schedule) {
                        $next->setDate($timeyear, $timemonth, $maxdays);
                    } else {
                        $next->setDate($timeyear, $timemonth, $schedule);
                    }
                    if ($next->getTimestamp() <= $this->time) {
                        $nextmonth = $timemonth + 1; // This rolls over into next year if necessary.
                        $next->setDate($timeyear, $nextmonth, 1);
                        $maxdays = $next->format('t');
                        if ($maxdays < $schedule) {
                            $next->setDate($timeyear, $nextmonth, $maxdays);
                        } else {
                            $next->setDate($timeyear, $nextmonth, $schedule);
                        }
                    }
                }
                break;
        }

        $this->subject->{$this->map['nextevent']} = $next->getTimestamp();
        return $this;
    }

    /**
     * Check if it's time to run event
     *
     * @return bool
     */
    public function is_time() {
        return $this->subject->{$this->map['nextevent']} < $this->time;
    }

    /**
     * Is there any changes to object made by scheduler
     *
     * @return bool
     */
    public function is_changed() {
        return $this->changed;
    }

    /**
     * Get available scheduler options
     *
     * @return array
     */
    public static function get_options() {
        return array('daily' => self::DAILY,
                     'weekly' => self::WEEKLY,
                     'monthly' => self::MONTHLY,
                     'hourly' => self::HOURLY,
                     'minutely' => self::MINUTELY,
        );
    }

    /**
     * Given scheduled report frequency and schedule data, output a human readable string.
     *
     * @param \stdClass $user - ignored
     * @return string Human readable string describing the schedule
     */
    public function get_formatted($user = null) {
        // Use a fixed date to prevent problems on days with DST switch and months with < 31 days.
        $date = new \DateTime('2000-01-01T00:00:00+00:00');

        if (!empty($user->lang)) {
            $lang = $user->lang;
        } else {
            $lang = current_language();
        }

        $calendardays = calendar_get_days(); // This is not localised properly, bad luck.
        $out = '';
        $schedule = $this->subject->{$this->map['schedule']};

        switch($this->subject->{$this->map['frequency']}) {
            case self::MINUTELY:
                $out .= new \lang_string('scheduledminutely', 'tool_etl', $schedule, $lang);
                break;
            case self::HOURLY:
                $out .= new \lang_string('scheduledhourly', 'tool_etl', $schedule, $lang);
                break;
            case self::DAILY:
                $date->setTime($schedule, 0, 0);
                date_default_timezone_set('UTC');
                $out .= new \lang_string('scheduleddaily', 'tool_etl',
                    strftime(get_string('strftimetime', 'langconfig'), $date->getTimestamp()), $lang);
                \core_date::set_default_server_timezone();
                break;
            case self::WEEKLY:
                $out .= new \lang_string('scheduledweekly', 'tool_etl',  $calendardays[$schedule]['fullname'], $lang);
                break;
            case self::MONTHLY:
                $dateformat = ($lang === 'en') ? 'jS' : 'j';
                $date->setTime(0, 0, 0);
                $date->setDate(2000, 1, $schedule);
                $out .= new \lang_string('scheduledmonthly', 'tool_etl', $date->format($dateformat), $lang);
                break;
        }

        return $out;
    }

    /**
     * Return timestamp when scheduled event is going to run
     * @return int timestamp
     */
    public function get_scheduled_time() {
        return $this->subject->{$this->map['nextevent']};
    }

    /**
     * Populate data based on initial array
     *
     * Compatible with scheduler form element data @see MoodleQuickForm_scheduler::exportValue()
     *
     * @param array $data - array with schedule parameters. If not set, default schedule will be applied
     */
    public function from_array(array $data = array()) {
        global $CFG;

        $this->changed = true;

        $data['frequency'] = isset($data['frequency']) ? $data['frequency'] : self::DAILY;
        $data['schedule'] = isset($data['schedule']) ? $data['schedule'] : 0;
        $data['initschedule'] = isset($data['initschedule']) ? $data['initschedule'] : false;
        $this->subject->{$this->map['frequency']} = $data['frequency'];
        $this->subject->{$this->map['schedule']} = $data['schedule'];
        // If no need in reinitialize, don't change nextreport value.
        if ($data['initschedule']) {
            $this->subject->{$this->map['nextevent']} = $this->time - 1;
        } else {
            $this->next();
        }
    }

    /**
     * Export scheduler parameters as an array
     * @return array
     */
    public function to_array() {
        $result = array(
                        'frequency' => $this->subject->{$this->map['frequency']},
                        'schedule' => $this->subject->{$this->map['schedule']},
                        'nextevent' => $this->subject->{$this->map['nextevent']},
                        'initschedule' => ($this->subject->{$this->map['nextevent']} <= $this->time)
        );
        return $result;
    }

    /**
     * Export scheduler parameters as an object
     *
     * Useful for saving in DB
     * @param array|string $extrafields primary key name and other fields to export
     * @return \stdClass
     */
    public function to_object($extrafields = 'id') {
        if (!is_array($extrafields)) {
            $extrafields = array($extrafields);
        }

        $obj = new \stdClass();
        $obj->{$this->map['nextevent']} = $this->subject->{$this->map['nextevent']};
        $obj->{$this->map['frequency']} = $this->subject->{$this->map['frequency']};
        $obj->{$this->map['schedule']} = $this->subject->{$this->map['schedule']};
        foreach ($extrafields as $field) {
            if (isset($this->subject->$field)) {
                $obj->$field = $this->subject->$field;
            }
        }
        return $obj;
    }
}
