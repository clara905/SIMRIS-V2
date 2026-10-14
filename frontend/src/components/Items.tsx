import { useEffect, useState } from "react";
import { api } from "../lib/api";
import type { Item, PaginatedResponse } from "../types/item";

export default function Items() {
  const [items, setItems] = useState<Item[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    api
      .get<PaginatedResponse<Item>>("/items")
      .then((response) => {
        setItems(response.data.data);
      })
      .catch((err) => {
        console.error(err);
        setError("Gagal mengambil data dari API Laravel.");
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  if (loading) return <p>Memuat data...</p>;
  if (error) return <p>{error}</p>;

  return (
    <div>
      <h1>Daftar Items</h1>

      {items.length === 0 ? (
        <p>Belum ada data item.</p>
      ) : (
        <ul>
          {items.map((item) => (
            <li key={item.id}>
              {item.name}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}