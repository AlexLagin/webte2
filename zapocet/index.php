<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pacienti</title>
  <style>
    table {
      border-collapse: collapse;
      width: 100%;
      margin-top: 20px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 8px;
      text-align: left;
    }
    th {
      background-color: #eee;
    }
    form {
      margin-bottom: 20px;
      max-width: 400px;
    }
    label {
      display: block;
      margin-top: 10px;
    }
    input {
      width: 100%;
      padding: 5px;
      box-sizing: border-box;
    }
    button {
      margin-top: 10px;
      padding: 8px 16px;
    }
    #result {
      margin-top: 10px;
      padding: 10px;
      background-color: #f0f0f0;
      white-space: pre-wrap;
      max-width: 400px;
    }
  </style>
</head>
<body>
  <h1>Pacienti</h1>
  
  <!-- Formulár na pridanie nového pacienta -->
  <form id="patientForm">
    <label for="first_name">Meno:</label>
    <input type="text" id="first_name" name="first_name" required>
    
    <label for="last_name">Priezvisko:</label>
    <input type="text" id="last_name" name="last_name" required>
    
    <label for="diagnose">Diagnóza:</label>
    <input type="text" id="diagnose" name="diagnose" required>
    
    <label for="birth_year">Dátum narodenia:</label>
    <input type="date" id="birth_year" name="birth_year" required>
    
    <label for="appointment">Appointment:</label>
    <input type="text" id="appointment" name="appointment">
    
    <button type="submit">Pridať pacienta</button>
  </form>
  
  <div id="result"></div>
  
  <!-- Tabuľka pre výpis pacientov -->
  <table id="patients-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Meno a Priezvisko</th>
        <th>Diagnóza</th>
        <th>Dátum narodenia</th>
        <th>Appointment</th>
      </tr>
    </thead>
    <tbody>
      <!-- Dynamicky naplnené riadky -->
    </tbody>
  </table>

  <script>
    // Získa a zobrazí zoznam pacientov
    async function fetchPatients() {
      try {
        // GET požiadavka na /zapocet/api.php?route=patients
        const response = await fetch('/zapocet/api.php?route=patients');
        const data = await response.json(); // očakávame JSON
        const tbody = document.querySelector('#patients-table tbody');
        tbody.innerHTML = "";
        
        data.forEach(patient => {
          const tr = document.createElement('tr');

          const tdId = document.createElement('td');
          tdId.textContent = patient.id || "";
          
          const tdFullname = document.createElement('td');
          tdFullname.textContent = patient.fullname || "";
          
          const tdDiagnose = document.createElement('td');
          tdDiagnose.textContent = patient.diagnose || "";
          
          const tdBirthYear = document.createElement('td');
          tdBirthYear.textContent = patient.birth_year || "";
          
          const tdAppointment = document.createElement('td');
          tdAppointment.textContent = patient.appointment || "";
          
          tr.appendChild(tdId);
          tr.appendChild(tdFullname);
          tr.appendChild(tdDiagnose);
          tr.appendChild(tdBirthYear);
          tr.appendChild(tdAppointment);
          
          tbody.appendChild(tr);
        });
      } catch (error) {
        console.error('Error fetching patients:', error);
      }
    }

    // Spracovanie formulára na pridanie nového pacienta
    document.getElementById('patientForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const first_name  = document.getElementById('first_name').value;
      const last_name   = document.getElementById('last_name').value;
      const diagnose    = document.getElementById('diagnose').value;
      const birth_year  = document.getElementById('birth_year').value;
      const appointment = document.getElementById('appointment').value;
      
      const data = {
        first_name: first_name,
        last_name: last_name,
        diagnose: diagnose,
        birth_year: birth_year,
        appointment: appointment
      };
      
      try {
        // POST požiadavka na /zapocet/api.php?route=patients
        const response = await fetch('/zapocet/api.php?route=patients', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });
        // Prečítame odpoveď ako JSON
        const result = await response.json();
        
        // Zobrazíme výsledok v #result
        document.getElementById('result').textContent = JSON.stringify(result, null, 2);
        
        // Po pridaní pacienta obnovíme tabuľku
        fetchPatients();
      } catch (error) {
        document.getElementById('result').textContent = 'Chyba: ' + error;
      }
    });

    // Načítanie pacientov pri načítaní stránky
    fetchPatients();
  </script>
</body>
</html>
