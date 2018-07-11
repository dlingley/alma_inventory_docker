# alma_inventory_docker
Alma Inventory API setup which can be launched using docker on Mac or Linux.  Issue with Windows 10 and docker and mapped drives prevent it from working currently on Windows 10.  For Windows 10 version see this branch: https://github.com/dlingley/alma_inventory_docker/tree/Windows-Version


1.
Clone Repo

2.
Add Alma key with Prod read-only access to Bibs and Configuration: https://www.screencast.com/t/x2RK4R5JaMwh

3.
Install Docker if not already installed

4. 
Run "docker-compose up" in project home directory

5.
Navigate to localhost:8080 in your browser and you should be able to see your list of libraries in the drop down list.

The docker config for this was base off of these instructions: https://bitpress.io/simple-approach-using-docker-with-php/
