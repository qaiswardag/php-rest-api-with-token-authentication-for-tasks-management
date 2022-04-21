<?php
// Task Model Object

// empty TaskException class so we can catch task errors
class TaskException extends Exception
{
}

class Task
{
    // define variable to store task id number
    private $_id;
    // define variable to store task title
    private $_title;
    // define variable to store task description
    private $_description;
    // define variable to store task deadline date
    private $_deadline;
    // define variable to store task completed
    private $_completed;


    // constructor to create the task object with the instance variables already set
    public function __construct($id, $title, $description, $deadline, $completed)
    {
        $this->setID($id);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setDeadline($deadline);
        $this->setCompleted($completed);
    }

    // function to return task ID
    public function getID()
    {
        return $this->_id;
    }

    // function to return task title
    public function getTitle()
    {
        return $this->_title;
    }

    // function to return task description
    public function getDescription()
    {
        return $this->_description;
    }

    // function to return task deadline
    public function getDeadline()
    {
        return $this->_deadline;
    }

    // function to return task completed
    public function getCompleted()
    {
        return $this->_completed;
    }

    // function to set the private task ID
    public function setID($id)
    {
        // if passed in task ID is not null or not numeric, is not between 0 and 9223372036854775807 (signed bigint max val - 64bit)
        // over nine quintillion rows
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new TaskException("Task ID error");
        }
        $this->_id = $id;
    }

    // function to set the private task title
    public function setTitle($title)
    {
        // if passed in title is not between 1 and 255 characters
        if (strlen($title) < 1 || strlen($title) > 255) {
            throw new TaskException("Task title error");
        }
        $this->_title = $title;
    }

    // function to set the private task description
    public function setDescription($description)
    {

        if ($description !== null) {
            if ((strlen($description) == 0 || strlen($description) > 16777215)) {
                throw new TaskException("Task description error");
            }
            $this->_description = $description;
        }
    }


    // public function to set the private task deadline date and time
    public function setDeadline($deadline)
    {
        if ($deadline !== null) {
            if (!date_create_from_format('d/m/Y H:i', $deadline) || date_format(date_create_from_format('d/m/Y H:i', $deadline), 'd/m/Y H:i') != $deadline) {
                throw new TaskException("Task deadline date and time error");
            }
            $this->_deadline = $deadline;
        }
    }

    // function to set the private task completed
    public function setCompleted($completed)
    {
        // if passed in completed is not Y or N
        if (strtoupper($completed) !== 'Y' && strtoupper($completed) !== 'N') {
            throw new TaskException("Task completed is not Y or N");
        }
        $this->_completed = strtoupper($completed);
    }


    // function to return task object as an array for json
    public function returnTaskAsArray()
    {
        $task = array();
        $task['id'] = $this->getID();
        $task['title'] = $this->getTitle();
        $task['description'] = $this->getDescription();
        $task['deadline'] = $this->getDeadline();
        $task['completed'] = $this->getCompleted();
        return $task;
    }

}
