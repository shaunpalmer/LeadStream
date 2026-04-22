<?php
namespace LeadStream\Admin\Dashboard;

use LeadStream\Admin\Dashboard\Data;
use LeadStream\Admin\Dashboard\Status;

class Factory {
    public function data(): Data {
        return new Data();
    }

    public function status(): Status {
        return new Status();
    }
}