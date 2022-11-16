<?php

    class TaskException extends Exception {} //All exceptions from utilization of this class will be stored here

    class Task {

        //initialize
        private $_id;
        private $_title;
        private $_description;
        private $_deadline;
        private $_completed;

        //constructor (we use double underscore to preceed constructor name)
        public function __construct($id, $title, $desc, $deadline, $completed){
            $this->setId($id);
            $this->setTitle($title);
            $this->setDesc($desc);
            $this->setDeadline($deadline);
            $this->setCompleted($completed);
        }

        //getters
        public function getId (){
            return $this->_id;
        }
        public function getTitle (){
            return $this->_title;
        }
        public function getDesc (){
            return $this->_description;
        }
        public function getDeadline (){
            return $this->_deadline;
        }
        public function getCompleted (){
            return $this->_completed;
        }

        //setters
        public function setId($id) {
            if(($id !== null) && ($id < 0 || !is_numeric($id) || $this->_id !== null)) {
                throw new TaskException("Task ID error");
            }
            $this->_id = $id;
        }

        public function setTitle($title) {
            if($title == null || strlen($title) > 255) {
                throw new TaskException("Task title error");
            }
            $this->_title = $title;
        }

        public function setDesc($desc) {
            if(($desc !== null) && strlen($desc) > 16777215) {
                throw new TaskException("Task description error");
            }
            $this->_description = $desc;
        }

        public function setDeadline($deadline) {

            $createDate = date_create_from_format('d/m/Y H:i', $deadline);

            if(($deadline !== null) && (date_format($createDate, 'd/m/Y H:i') != $deadline)) {
                throw new TaskException("Task deadline datetime error");
            }
            $this->_deadline = $deadline;
        }

        public function setCompleted($completed) {
            if(strtoupper($completed !== "Y") && strtoupper($completed !== "N")) {
                throw new TaskException("Task completion status must be Y or N");
            }
            $this->_completed = $completed;
        }

        //convert to a JSON friendly array. This is called a helper method
        public function returnTaskAsArray() {
            $task = array();
            $task['id'] = $this->getId();
            $task['title'] = $this->getTitle();
            $task['description'] = $this->getDesc();
            $task['deadline'] = $this->getDeadline();
            $task['completed'] = $this->getCompleted();

            return $task;
        }
    }

?>