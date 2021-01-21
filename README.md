# FileManager API Server

### [API Documentation](https://documenter.getpostman.com/view/1155743/SWLk2k46)


## Testing
```php
$dir = __DIR__;
echo "Changing dir to: {$dir}".PHP_EOL;
$cmd = "cd {$dir} && php -S 127.0.0.1:8000";

$proc = popen($cmd, 'r');

while ( ! feof($proc)) {
    echo fread($proc, 1024);
    @flush();
}
```
