<?php
interface SP_Newsletter_Sender {
    public function send_digest( string $html, string $subject ): bool;
}
