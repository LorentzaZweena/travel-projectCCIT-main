Travel Web Application

A web-based travel application that helps users discover and explore travel destinations through an interactive and user-friendly interface. This application is built using Native PHP, Bootstrap, MySQL, and Leaflet.js. Users can browse travel destinations, view detailed destination information, and explore destination locations through an interactive map.

✨ Features

- Browse travel destinations
- View detailed information about destinations
- Explore destination locations using an interactive map
- Display destination markers using Leaflet.js
- Search and explore available destinations
- Responsive interface using Bootstrap
- Store travel destination data using MySQL
- User-based application without admin roles

🛠️ Technologies Used
1. PHP	Backend development using Native PHP
2. MySQL	Database management system
3. Bootstrap	Responsive UI framework
4. Leaflet.js	Interactive map library
5. HTML5	Web page structure
6. CSS3	Custom styling
7. JavaScript	Client-side functionality

The project structure may vary depending on the implementation of the application.

🗺️ Interactive Map

The application uses Leaflet.js to display travel destinations on an interactive map. Each destination can contain geographical coordinates, including latitude and longitude. These coordinates are used to place markers on the map.

Example:
```
const map = L.map('map').setView([-6.1754, 106.8272], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

L.marker([-6.1754, 106.8272])
    .addTo(map)
    .bindPopup('Travel Destination');
```

The map allows users to visually explore the locations of available travel destinations.

🗄️ Database

The application uses MySQL to store information related to users and travel destinations.

🎯 Project Goals

The main goals of this project are:
1. Provide users with an easy way to discover travel destinations.
2. Present destination information in a simple and attractive interface.
3. Provide an interactive map for viewing destination locations.
4. Develop a responsive travel web application using Bootstrap.
5. Practice backend development using Native PHP and MySQL.
6. Integrate Leaflet.js for location-based features.

👨‍💻 Author

Developed by Ariva Zweena.
