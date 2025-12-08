# Admin Útmutató - Szavazási Rendszer

## Admin Fiók Bejelentkezés

### Admin Hitelesítő Adatok
- **Email:** `admin@example.com`
- **Jelszó:** `admin123`

### Bejelentkezés (Bearer Token megszerzése)

```bash
POST /api/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "admin123"
}
```

**Válasz:**
```json
{
  "access_token": "your-bearer-token-here",
  "token_type": "Bearer"
}
```

**Megjegyzés:** A Bearer tokent használd minden admin művelethez a `Authorization: Bearer {token}` header-ben.

---

## Admin Funkciók

### 1. Szavazás Módosítása

Meglévő szavazás adatainak módosítása (kérdés, leírás, opciók, lezárási dátum).

```bash
PUT /api/admin/polls/{poll_id}
Authorization: Bearer {your-token}
Content-Type: application/json

{
  "question": "Új kérdés szöveg",
  "description": "Új leírás",
  "options": ["Opció 1", "Opció 2", "Opció 3"],
  "closes_at": "2025-12-31 23:59:59"
}
```

**Válasz:**
```json
{
  "message": "Poll updated successfully",
  "poll": {
    "id": 1,
    "question": "Új kérdés szöveg",
    "description": "Új leírás",
    "options": ["Opció 1", "Opció 2", "Opció 3"],
    "closes_at": "2025-12-31 23:59:59",
    "created_at": "2025-12-08T10:00:00.000000Z",
    "updated_at": "2025-12-08T11:00:00.000000Z"
  }
}
```

---

### 2. Szavazás Törlése (Soft Delete)

Szavazás soft delete-tel való törlése. A szavazás nem törlődik véglegesen, csak rejtett lesz és később visszaállítható.

```bash
DELETE /api/admin/polls/{poll_id}
Authorization: Bearer {your-token}
```

**Válasz:**
```json
{
  "message": "Poll soft deleted successfully"
}
```

**Megjegyzés:** A szavazás csak elrejtésre kerül, nem törlődik véglegesen. Használd a restore funkciót a visszaállításhoz.

---

### 2a. Törölt Szavazások Listázása

Az összes soft delete-tel törölt szavazás lekérése.

```bash
GET /api/admin/polls/trashed
Authorization: Bearer {your-token}
```

**Válasz:**
```json
{
  "deleted_polls": [
    {
      "id": 1,
      "question": "Törölt szavazás",
      "deleted_at": "2025-12-08T10:30:00.000000Z",
      "...": "..."
    }
  ]
}
```

---

### 2b. Szavazás Visszaállítása

Soft delete-tel törölt szavazás visszaállítása.

```bash
POST /api/admin/polls/{poll_id}/restore
Authorization: Bearer {your-token}
```

**Válasz:**
```json
{
  "message": "Poll restored successfully",
  "poll": {
    "id": 1,
    "question": "Visszaállított szavazás",
    "deleted_at": null,
    "...": "..."
  }
}
```

---

### 2c. Szavazás Végleges Törlése

Szavazás végleges törlése az adatbázisból (nem visszaállítható!).

```bash
DELETE /api/admin/polls/{poll_id}/force
Authorization: Bearer {your-token}
```

**Válasz:**
```json
{
  "message": "Poll permanently deleted"
}
```

**⚠️ FIGYELEM:** Ez a művelet véglegesen törli a szavazást és az összes hozzá tartozó szavazatot. Nem visszaállítható!

---

### 3. Szavazás Lezárása (Azonnal)

Szavazás azonnali lezárása - a lezárási dátum az aktuális időpontra állítódik.

```bash
POST /api/admin/polls/{poll_id}/close
Authorization: Bearer {your-token}
```

**Válasz:**
```json
{
  "message": "Poll closed successfully",
  "poll": {
    "id": 1,
    "question": "Kérdés szöveg",
    "closes_at": "2025-12-08T10:30:00.000000Z",
    "...": "..."
  }
}
```

---

### 4. Szavazás Lezárási Dátumának Meghosszabbítása

Új lezárási dátum beállítása a szavazásnak (a jövőben).

```bash
POST /api/admin/polls/{poll_id}/extend
Authorization: Bearer {your-token}
Content-Type: application/json

{
  "closes_at": "2025-12-31 23:59:59"
}
```

**Válasz:**
```json
{
  "message": "Poll deadline extended successfully",
  "poll": {
    "id": 1,
    "question": "Kérdés szöveg",
    "closes_at": "2025-12-31 23:59:59",
    "...": "..."
  }
}
```

**Megjegyzés:** A `closes_at` értéknek a jövőben kell lennie.

---

### 5. Szavazás Újranyitása (Lezárási Dátum Eltávolítása)

Lezárási dátum eltávolítása, így a szavazás határozatlan ideig nyitva marad.

```bash
POST /api/admin/polls/{poll_id}/open
Authorization: Bearer {your-token}
```

**Válasz:**
```json
{
  "message": "Poll opened (no closing date)",
  "poll": {
    "id": 1,
    "question": "Kérdés szöveg",
    "closes_at": null,
    "...": "..."
  }
}
```

---

## Postman Példák

### 1. Bejelentkezés és Token Megszerzése

1. Új Request: `POST http://localhost:8000/api/login`
2. Body → raw → JSON:
   ```json
   {
     "email": "admin@example.com",
     "password": "admin123"
   }
   ```
3. Send
4. Másold ki az `access_token` értéket a válaszból

### 2. Admin Művelet Végrehajtása

1. Új Request: `PUT http://localhost:8000/api/admin/polls/1`
2. Headers → Add:
   - Key: `Authorization`
   - Value: `Bearer {az-előbb-kapott-token}`
3. Body → raw → JSON:
   ```json
   {
     "question": "Módosított kérdés"
   }
   ```
4. Send

### 3. Törölt Szavazások Megtekintése

1. Új Request: `GET http://localhost:8000/api/admin/polls/trashed`
2. Headers → Add:
   - Key: `Authorization`
   - Value: `Bearer {token}`
3. Send

### 4. Szavazás Visszaállítása

1. Új Request: `POST http://localhost:8000/api/admin/polls/1/restore`
2. Headers → Add:
   - Key: `Authorization`
   - Value: `Bearer {token}`
3. Send

---

## Hibakezelés

### 403 Forbidden - Nincs Admin Jogosultság
```json
{
  "message": "Unauthorized. Admin access required."
}
```

**Ok:** A bejelentkezett felhasználó nem admin.

### 401 Unauthorized - Nincs Bejelentkezve
```json
{
  "message": "Unauthenticated."
}
```

**Ok:** Nincs Bearer token vagy lejárt.

### 404 Not Found - Szavazás Nem Található
```json
{
  "message": "No query results for model [App\\Models\\Poll] {id}"
}
```

**Ok:** A megadott `poll_id` nem létezik.

### 422 Unprocessable Entity - Validációs Hiba
```json
{
  "message": "The closes at field must be a date after now.",
  "errors": {
    "closes_at": [
      "The closes at field must be a date after now."
    ]
  }
}
```

**Ok:** A megadott adatok nem felelnek meg a validációs szabályoknak.

---

## Tesztelés

Minden teszt sikeresen lefut:

```bash
php artisan test
```

**Eredmény:** 4 warnings, 1 passed (9 assertions) - Minden assertion sikeres!

---

## Biztonsági Megjegyzések

1. **Változtasd meg az admin jelszót éles környezetben!**
2. A Bearer token-ek érzékeny adatok, ne oszd meg őket.
3. Az admin funkciók csak az `is_admin = true` felhasználók számára érhetők el.
4. Az admin middleware minden admin endpoint előtt ellenőrzi a jogosultságokat.

---

## Adatbázis Módosítások

Az admin funkció hozzáadásához a következő módosítások történtek:

1. **Migration:** `add_is_admin_to_users_table` - `is_admin` boolean mező hozzáadása
2. **User Model:** `is_admin` fillable és cast beállítása
3. **Middleware:** `IsAdmin` - admin jogosultság ellenőrzése
4. **Seeder:** `AdminUserSeeder` - admin felhasználó létrehozása

---

## Támogatás

Ha problémába ütközöl, ellenőrizd:
- ✅ Sikeres bejelentkezés és Bearer token megszerzése
- ✅ Bearer token helyes használata az Authorization header-ben
- ✅ Admin jogosultság (is_admin = true) a felhasználónál
- ✅ Helyes API endpoint címek
- ✅ Helyes HTTP method használata (PUT, POST, DELETE)
