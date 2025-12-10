# Szavazási rendszer program napló


## 1. Adminisztrátor funkciók

### 1.1 Admin fiók bejelentkezés

Az adminisztrátor a rendszerbe Bearer token alapú hitelesítéssel léphet be.

**Endpoint:** `POST /api/login`

**Admin hitelesítő adatok:**
- Email: `admin@example.com`
- Jelszó: `admin123`

**Request:**
```json
{
  "email": "admin@example.com",
  "password": "admin123"
}
```

**Response:**
```json
{
  "access_token": "bearer-token",
  "token_type": "Bearer"
}
```

**Megjegyzés:** A visszakapott tokent minden admin művelethez hozzá kell csatolni az `Authorization: Bearer {token}` header-ben.

---

### 1.2 Admin műveletek szavazásokon

#### Szavazás módosítása

**Endpoint:** `PUT /api/admin/polls/{poll_id}`

**Funkció:** Meglévő szavazás adatainak módosítása (kérdés, leírás, opciók, lezárási dátum).

**Controller:** `App\Http\Controllers\Api\AdminPollController@update`

**Kódrészlet:**
```php
public function update(Request $request, Poll $poll): JsonResponse
{
    $data = $request->validate([
        'question' => ['sometimes', 'required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'options' => ['sometimes', 'required', 'array', 'min:2'],
        'options.*' => ['string'],
        'closes_at' => ['nullable', 'date'],
    ]);

    if (isset($data['options'])) {
        $data['options'] = array_values(array_filter($data['options'], fn($s) => trim($s) !== ''));
    }

    $poll->update($data);

    return response()->json([
        'message' => 'Poll updated successfully',
        'poll' => $poll->fresh()
    ]);
}
```

**Működés:**
- Validálja a bejövő adatokat
- Az opciókat array formátumban várja, eltávolítja az üres értékeket
- Frissíti a szavazás adatait az adatbázisban
- Visszaadja a módosított szavazást

---

#### Szavazás azonnali lezárása

**Endpoint:** `POST /api/admin/polls/{poll_id}/close`

**Funkció:** A szavazás lezárási dátumát az aktuális időpontra állítja, így azonnal lezárja.

**Controller:** `App\Http\Controllers\Api\AdminPollController@close`

**Kódrészlet:**
```php
public function close(Poll $poll): JsonResponse
{
    $poll->update([
        'closes_at' => now()
    ]);

    return response()->json([
        'message' => 'Poll closed successfully',
        'poll' => $poll->fresh()
    ]);
}
```

**Működés:**
- A `closes_at` mezőt az aktuális időpontra (`now()`) állítja
- Onnantól kezdve a felhasználók nem tudnak rá szavazni

---

#### Szavazás határidejének meghosszabbítása

**Endpoint:** `POST /api/admin/polls/{poll_id}/extend`

**Funkció:** Új lezárási dátum beállítása a szavazáshoz.

**Controller:** `App\Http\Controllers\Api\AdminPollController@extend`

**Kódrészlet:**
```php
public function extend(Request $request, Poll $poll): JsonResponse
{
    $data = $request->validate([
        'closes_at' => ['required', 'date', 'after:now']
    ]);

    $poll->update([
        'closes_at' => $data['closes_at']
    ]);

    return response()->json([
        'message' => 'Poll deadline extended successfully',
        'poll' => $poll->fresh()
    ]);
}
```

**Működés:**
- Validálja hogy a megadott dátum a jövőben van
- Frissíti a `closes_at` mezőt az új dátummal
- Lehetővé teszi lezárt szavazások újranyitását

---

#### Szavazás megnyitása (határidő eltávolítása)

**Endpoint:** `POST /api/admin/polls/{poll_id}/open`

**Funkció:** Eltávolítja a lezárási dátumot, így a szavazás korlátlan ideig nyitva marad.

**Controller:** `App\Http\Controllers\Api\AdminPollController@open`

**Kódrészlet:**
```php
public function open(Poll $poll): JsonResponse
{
    $poll->update([
        'closes_at' => null
    ]);

    return response()->json([
        'message' => 'Poll opened (no closing date)',
        'poll' => $poll->fresh()
    ]);
}
```

**Működés:**
- A `closes_at` mezőt `null` értékre állítja
- A szavazás nincs időkorláthoz kötve

---

## 2. Soft delete (lágy törlés)

### 2.1 Szavazás soft delete-elése

**Endpoint:** `DELETE /api/admin/polls/{poll_id}`

**Funkció:** A szavazás "lágyan" törlődik, vagyis nem kerül véglegesen eltávolításra az adatbázisból.

**Controller:** `App\Http\Controllers\Api\AdminPollController@destroy`

**Kódrészlet:**
```php
public function destroy(Poll $poll): JsonResponse
{
    $poll->delete(); // Soft delete

    return response()->json([
        'message' => 'Poll soft deleted successfully'
    ]);
}
```

**Működés:**
- Laravel SoftDeletes trait használata
- A `deleted_at` oszlopba beírja az aktuális időpontot
- A szavazás nem jelenik meg a normál lekérdezésekben
- Az adatok megmaradnak és később visszaállíthatók

**Model implementáció:**
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Poll extends Model
{
    use SoftDeletes;
}
```

**Indoklás:** Véletlenül törölt vagy archivált szavazások visszaállíthatósága adatvesztés nélkül.

---

### 2.2 Törölt szavazások listázása

**Endpoint:** `GET /api/admin/polls/trashed`

**Funkció:** Az összes soft delete-elt szavazás listázása törlési dátum szerint csökkenő sorrendben.

**Controller:** `App\Http\Controllers\Api\AdminPollController@trashed`

**Kódrészlet:**
```php
public function trashed(): JsonResponse
{
    $polls = Poll::onlyTrashed()->orderByDesc('deleted_at')->get();
    
    return response()->json([
        'deleted_polls' => $polls
    ]);
}
```

**Működés:**
- `Poll::onlyTrashed()` query scope használata
- Csak a `deleted_at IS NOT NULL` szavazásokat kérdezi le
- `deleted_at` szerint rendezi őket

---

### 2.3 Soft delete-elt szavazás visszaállítása

**Endpoint:** `POST /api/admin/polls/{poll_id}/restore`

**Funkció:** Törölt szavazás visszaállítása aktív állapotba.

**Controller:** `App\Http\Controllers\Api\AdminPollController@restore`

**Kódrészlet:**
```php
public function restore(int $id): JsonResponse
{
    $poll = Poll::withTrashed()->findOrFail($id);
    
    if (!$poll->trashed()) {
        return response()->json([
            'message' => 'Poll is not deleted'
        ], 400);
    }

    $poll->restore();

    return response()->json([
        'message' => 'Poll restored successfully',
        'poll' => $poll->fresh()
    ]);
}
```

**Működés:**
- `Poll::withTrashed()->findOrFail($id)` segítségével megtalálja a törölt szavazást
- Ellenőrzi hogy valóban törölve van-e
- `restore()` metódussal a `deleted_at` mezőt `NULL`-ra állítja
- A szavazás újra elérhető lesz normál lekérdezésekben

---

## 3. Végleges törlés (force delete)

### 3.1 Szavazás végleges törlése

**Endpoint:** `DELETE /api/admin/polls/{poll_id}/force`

**Funkció:** A szavazás és az összes hozzá tartozó szavazat végleges törlése az adatbázisból.

**Controller:** `App\Http\Controllers\Api\AdminPollController@forceDestroy`

**Kódrészlet:**
```php
public function forceDestroy(int $id): JsonResponse
{
    $poll = Poll::withTrashed()->findOrFail($id);
    
    // Delete all related votes permanently
    $poll->votes()->delete();
    
    // Force delete the poll
    $poll->forceDelete();

    return response()->json([
        'message' => 'Poll permanently deleted'
    ]);
}
```

**Működés:**
1. Megtalálja a szavazást függetlenül attól hogy soft delete-elve van-e (`withTrashed()`)
2. Először törli az összes kapcsolódó szavazatot: `$poll->votes()->delete()`
3. Ezután véglegesen törli a szavazást: `$poll->forceDelete()`
4. Az adatok véglegesen eltűnnek az adatbázisból

**Figyelmeztetés:** Ez a művelet visszafordíthatatlan, minden adat elveszik!

**Használati eset:** 
- Biztosan nem szükséges adatok eltávolítása
- Adatbázis méretének csökkentése
- GDPR compliance (adatok végleges törlése)

---

## Összefoglalás

A rendszer két szintű törlési mechanizmust valósít meg:

1. **Soft delete:** Biztonságos törlés visszaállítási lehetőséggel, ideális napi használatra
2. **Force delete:** Végeleges törlés, csak akkor amikor biztosan nem kell az adat

Az admin funkciók teljes kontrollt biztosítanak a szavazások életciklusa felett: létrehozás, módosítás, lezárás, meghosszabbítás, törlés és visszaállítás.
