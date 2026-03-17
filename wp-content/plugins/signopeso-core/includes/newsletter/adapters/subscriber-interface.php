<?php
interface SP_Newsletter_Subscriber {
    public function add_subscriber( string $email ): bool;
}
