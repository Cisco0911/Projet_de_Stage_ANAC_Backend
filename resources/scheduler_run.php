<?php
chdir('/home/u615318242/public_html/api');
exec('php artisan schedule:run >> /dev/null 2>&1');
