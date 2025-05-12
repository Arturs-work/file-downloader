# File downloader assignment

## Development Setup

1. Make sure you have Docker installed on your system
2. Clone the repository
3. Start the development environment:
   ```bash
   docker-compose up --build
   ```

## Tech Stack

1. Symfony
2. Doctrine
3. ReactPHP
4. MySQL
4. Docker

## Requirements implemented

1. Application can download multiple (5 at the moment, but configurable) files at the same time
2. It can handle network interuptions and resume file downloading when connection is back again. Uses `Range` header to resume downloads
3. It has a backoff logic for retrying failed downloads (10, 20, 30 seconds) and then failing the download
4. The download state of each file is saved in database in case of process failure and can be resume again
5. Additional error and status information on each file is also saved in database

## Running it

1. Run `bin/console files:download` to run the console command manually
You can also run `bin/console files:download --file=./urls.txt` with additional flag to import some urls to be added to queue from supplied text file.
2. Observe the urls added to db as files to process and see the progress of each file in the console output
3. Profit...

## Test

Did not have much time to expand on those, but added a few very simple ones.
`bin/phpunit` to run it.

## Future improvements

1. Although I added the additional logic to look for more files to process (and it works!) while the event loop is running, there were odd bugs with logging correctly to console, which I didnt have enough time to fix.
2. Im sure a few more edge cases could be discovered and fixed with additional validation and logic
3. More graceful shutdowns and adding supervisor to restart on crash, reboot or memory limit.
4. Could add dashboard and use websockets to show the download queue in real-time
