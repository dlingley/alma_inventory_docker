# alma_inventory_docker
Alma Inventory API setup which can be launched using docker on Mac, Linux or Windows.
The details of this application and how to run it can be found here: https://developers.exlibrisgroup.com/blog/Shelf-Inventory-using-Alma-APIs


1.
Clone Repo

2.
Add Alma key with Prod read-only access to Bibs and Configuration: https://www.screencast.com/t/x2RK4R5JaMwh

3.
Install Docker if not already installed: https://www.docker.com/products/docker-desktop

4. 
Run "docker-compose up" in project home directory

5.
Navigate to http://localhost:8080 in your browser and you should be able to see your list of libraries in the drop down list.

The docker config for this installation was based off of these instructions: https://bitpress.io/simple-approach-using-docker-with-php/
