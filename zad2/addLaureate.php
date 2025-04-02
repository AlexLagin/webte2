<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pridať laureáta</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
      padding-top: 20px;
    }
    .form-section {
      margin-bottom: 20px;
    }
    .hidden {
      display: none !important;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1 class="mb-4">Pridať nového laureáta</h1>
    <form id="addLaureateForm">
      <!-- Sekcia údajov o laureátovi -->
      <div class="card mb-4">
        <div class="card-header">
          Údaje o laureátovi
        </div>
        <div class="card-body">
          <div class="mb-3" id="fullnameContainer">
            <label for="fullname" class="form-label">Celé meno</label>
            <input type="text" class="form-control" id="fullname" name="fullname" required>
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
          <div class="mb-3">
            <label for="birth_year" class="form-label">Rok narodenia</label>
            <input type="number" class="form-control" id="birth_year" name="birth_year" required>
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
            <select class="form-select" id="prize_category" name="prize_category" required>
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
            <input type="number" class="form-control" id="prize_year" name="prize_year" required>
          </div>
          <div class="mb-3">
            <label for="award" class="form-label">Ocenenie</label>
            <textarea class="form-control" id="award" name="award" rows="2" required></textarea>
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
    // Funkcia na prepínanie viditeľnosti polí "Celé meno" a "Organizácia"
    function toggleFields() {
      const fullnameField = document.getElementById('fullname');
      const organisationField = document.getElementById('organisation');
      const fullnameContainer = document.getElementById('fullnameContainer');
      const organisationContainer = document.getElementById('organisationContainer');
      const genderContainer = document.getElementById('genderContainer');

      const fullnameValue = fullnameField.value.trim();
      const organisationValue = organisationField.value.trim();

      if (fullnameValue !== "" && organisationValue === "") {
        // Ak je zadané "Celé meno" a "Organizácia" je prázdna, skry kontajner s Organizáciou a zobraz pohlavie
        organisationContainer.classList.add('hidden');
        fullnameContainer.classList.remove('hidden');
        genderContainer.classList.remove('hidden');
      } else if (organisationValue !== "" && fullnameValue === "") {
        // Ak je zadaná "Organizácia" a "Celé meno" je prázdne, skry kontajner s Celým menom a pohlavie
        fullnameContainer.classList.add('hidden');
        organisationContainer.classList.remove('hidden');
        genderContainer.classList.add('hidden');
      } else {
        // Ak sú obe prázdne, zobraz obe polia a skry pohlavie
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

    // Odoslanie formulára pomocou metódy POST
    document.getElementById('addLaureateForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const formData = {
        // Údaje o laureátovi
        fullname: document.getElementById('fullname').value.trim(),
        organisation: document.getElementById('organisation').value.trim() || null,
        birth: parseInt(document.getElementById('birth_year').value, 10) || null,
        death: document.getElementById('death_year').value ? parseInt(document.getElementById('death_year').value, 10) : null,
        gender: document.getElementById('gender').value || null,
        // Informácie o cene – predpokladáme, že API pre POST /laureates akceptuje aj pole "prizes"
        prizes: [
          {
            category: document.getElementById('prize_category').value,
            year: parseInt(document.getElementById('prize_year').value, 10) || null,
            award: document.getElementById('award').value.trim(),
            // Ak je zvolená kategória Literatúra, pridáme aj jazyk a žáner
            ...(document.getElementById('prize_category').value.toLowerCase() === 'literatúra' && {
              language: document.getElementById('language').value.trim(),
              genre: document.getElementById('genre').value.trim()
            })
          }
        ]
      };

      try {
        await fetch('/zad2/api/v0/laureates', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(formData)
        });
        // Po odoslaní formulára sa nevykonáva žiadne automatické presmerovanie
      } catch (error) {
        console.error('Chyba pri odosielaní formulára:', error);
      }
    });
  </script>
</body>
</html>
