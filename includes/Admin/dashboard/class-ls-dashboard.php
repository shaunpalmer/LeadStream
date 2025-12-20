<?php
namespace LeadStream\Admin\Dashboard;

use LeadStream\Admin\Dashboard\Factory;
use LeadStream\Admin\Dashboard\Data;
use LeadStream\Admin\Dashboard\Status;
use LeadStream\Admin\Dashboard\Render;

class Dashboard {
    private Factory $factory;

    public function __construct() {
        $this->factory = new Factory();
    }

    public function render(): void {
        $data = $this->factory->data();
        $status = $this->factory->status();
        (new Render($data, $status))->output();
    }
}