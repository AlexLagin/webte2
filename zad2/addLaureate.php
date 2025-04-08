<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pridať laureáta</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Odstránenie predvoleného odsadenia, aby navbar bol úplne hore */
    body {
      margin: 0;
      padding: 0;
      background-color: #f5f5f5;
    }
    .hidden {
      display: none !important;
    }
  </style>
</head>
<body>
  <!-- Navbar s prepojením na index.php, úplne hore -->
  <nav class="navbar navbar-dark bg-secondary">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">Prehľad laureátov</a>
    </div>
  </nav>

  <!-- Obsah stránky -->
  <div class="container">
    <h1 class="my-4">Pridať nového laureáta</h1>

    <!-- Alert pre chybové hlásenia z backendu -->
    <div id="errorAlert" class="alert alert-danger hidden" role="alert"></div>
    <!-- Alert pre úspešné hlásenia z backendu -->
    <div id="successAlert" class="alert alert-success hidden" role="alert"></div>

    <!-- Pridali sme novalidate, aby sme vypli HTML5 validáciu -->
    <form id="addLaureateForm" novalidate>
      <!-- Sekcia údajov o laureátovi -->
      <div class="card mb-4">
        <div class="card-header">
          Údaje o laureátovi
        </div>
        <div class="card-body">
          <div class="mb-3" id="fullnameContainer">
            <label for="fullname" class="form-label">Celé meno</label>
            <input type="text" class="form-control" id="fullname" name="fullname">
          </div>
          <div class="mb-3" id="organisationContainer">
            <label for="organisation" class="form-label">Organizácia</label>
            <input type="text" class="form-control" id="organisation" name="organisation">
          </div>
          <!-- Pohlavie je pôvodne skryté -->
          <div class="mb-3 hidden" id="genderContainer">
            <label for="gender" class="form-label">Pohlavie</label>
            <select class="form-select" id="gender" name="gender">
              <option value="">Vyberte...</option>
              <option value="M">Muž</option>
              <option value="F">Žena</option>
            </select>
          </div>
          <!-- Nové pole pre krajinu -->
          <div class="mb-3" id="countryContainer">
            <label for="country" class="form-label">Krajina</label>
            <input type="text" class="form-control" id="country" name="country">
          </div>
          <div class="mb-3">
            <label for="birth_year" class="form-label">Rok narodenia</label>
            <input type="number" class="form-control" id="birth_year" name="birth_year">
          </div>
          <div class="mb-3">
            <label for="death_year" class="form-label">Rok úmrtia (nechajte prázdne, ak je nažive)</label>
            <input type="number" class="form-control" id="death_year" name="death_year">
          </div>
        </div>
      </div>

      <!-- Sekcia cenových údajov -->
      <div class="card mb-4">
        <div class="card-header">
          Informácie o cene
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label for="prize_category" class="form-label">Kategória ceny</label>
            <select class="form-select" id="prize_category" name="prize_category">
              <option value="">Vyberte...</option>
              <option value="Mier">Mier</option>
              <option value="Literatúra">Literatúra</option>
              <option value="Chémia">Chémia</option>
              <option value="Fyzika">Fyzika</option>
              <option value="Medicína">Medicína</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="prize_year" class="form-label">Rok získania ceny</label>
            <input type="number" class="form-control" id="prize_year" name="prize_year">
          </div>
          <div class="mb-3">
            <label for="award" class="form-label">Ocenenie</label>
            <textarea class="form-control" id="award" name="award" rows="2"></textarea>
          </div>
          <!-- Sekcia pre literatúru: jazyk a žáner -->
          <div id="literatureFields" class="hidden">
            <div class="mb-3">
              <label for="language" class="form-label">Jazyk</label>
              <input type="text" class="form-control" id="language" name="language">
            </div>
            <div class="mb-3">
              <label for="genre" class="form-label">Žáner</label>
              <input type="text" class="form-control" id="genre" name="genre">
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Odoslať</button>
    </form>
  </div>

  <!-- Bootstrap 5 JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Funkcia pre prepínanie viditeľnosti polí "Celé meno" a "Organizácia"
    function toggleFields() {
      const fullnameField = document.getElementById('fullname');
      const organisationField = document.getElementById('organisation');
      const fullnameContainer = document.getElementById('fullnameContainer');
      const organisationContainer = document.getElementById('organisationContainer');
      const genderContainer = document.getElementById('genderContainer');

      const fullnameValue = fullnameField.value.trim();
      const organisationValue = organisationField.value.trim();

      if (fullnameValue !== "" && organisationValue === "") {
        organisationContainer.classList.add('hidden');
        fullnameContainer.classList.remove('hidden');
        genderContainer.classList.remove('hidden');
      } else if (organisationValue !== "" && fullnameValue === "") {
        fullnameContainer.classList.add('hidden');
        organisationContainer.classList.remove('hidden');
        genderContainer.classList.add('hidden');
      } else {
        fullnameContainer.classList.remove('hidden');
        organisationContainer.classList.remove('hidden');
        genderContainer.classList.add('hidden');
      }
    }

    document.getElementById('fullname').addEventListener('input', toggleFields);
    document.getElementById('organisation').addEventListener('input', toggleFields);

    // Zobrazenie alebo skrytie literatúrnych polí podľa zvolenej kategórie ceny
    const prizeCategorySelect = document.getElementById('prize_category');
    const literatureFields = document.getElementById('literatureFields');

    prizeCategorySelect.addEventListener('change', function() {
      if (this.value.toLowerCase() === 'literatúra') {
        literatureFields.classList.remove('hidden');
      } else {
        literatureFields.classList.add('hidden');
      }
    });

    // Odoslanie formulára pomocou POST s backend validáciou
    document.getElementById('addLaureateForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      // Skryť prípadné predchádzajúce hlásenia
      const errorAlert = document.getElementById('errorAlert');
      const successAlert = document.getElementById('successAlert');
      errorAlert.classList.add('hidden');
      errorAlert.textContent = "";
      successAlert.classList.add('hidden');
      successAlert.textContent = "";

      const formData = {
        fullname: document.getElementById('fullname').value.trim(),
        organisation: document.getElementById('organisation').value.trim() || null,
        birth: parseInt(document.getElementById('birth_year').value, 10) || null,
        death: document.getElementById('death_year').value ? parseInt(document.getElementById('death_year').value, 10) : null,
        gender: document.getElementById('gender').value || null,
        country: document.getElementById('country') ? document.getElementById('country').value.trim() : null,
        prizes: [
          {
            category: document.getElementById('prize_category').value,
            year: parseInt(document.getElementById('prize_year').value, 10) || null,
            award: document.getElementById('award').value.trim(),
            ...(document.getElementById('prize_category').value.toLowerCase() === 'literatúra' && {
              language: document.getElementById('language').value.trim(),
              genre: document.getElementById('genre').value.trim()
            })
          }
        ]
      };

      try {
        const response = await fetch('/zad2/api/v0/laureates', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (!response.ok) {
          // Zobrazí chybovú hlášku z backendu
          errorAlert.textContent = result.message || 'Chyba pri odosielaní formulára';
          errorAlert.classList.remove('hidden');
        } else {
          // Vytvoríme správu vrátanú z backendu
          let msg = result.message;
          if (result.data) {
            msg;
          }
          successAlert.textContent = msg;
          successAlert.classList.remove('hidden');
          // Vyčistenie formulára a obnovenie prepínania polí
          document.getElementById('addLaureateForm').reset();
          toggleFields();
        }
      } catch (error) {
        console.error('Chyba pri odosielaní formulára:', error);
        errorAlert.textContent = 'Chyba pri odosielaní formulára';
        errorAlert.classList.remove('hidden');
      }
    });
  </script>
</body>
</html>
