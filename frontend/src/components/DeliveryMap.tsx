"use client";

import { useEffect, useMemo } from "react";
import "leaflet/dist/leaflet.css";
import { MapContainer, Marker, Polyline, Popup, TileLayer, useMap } from "react-leaflet";
import L, { type LatLngExpression } from "leaflet";

type LatLng = { lat: number; lng: number };

/**
 * Ganti icon default Leaflet supaya tidak bergantung pada asset PNG
 * bundled dari CDN — kita bikin marker SVG inline (warna sesuai peran).
 */
function makeIcon(color: string): L.DivIcon {
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 32" width="26" height="34">
      <path fill="${color}" stroke="#111" stroke-width="1.5"
            d="M12 0C5.4 0 0 5.4 0 12c0 8.4 12 20 12 20s12-11.6 12-20C24 5.4 18.6 0 12 0z"/>
      <circle cx="12" cy="12" r="4.5" fill="#111"/>
    </svg>
  `.trim();
  return L.divIcon({
    className: "delivery-marker",
    html: svg,
    iconSize: [26, 34],
    iconAnchor: [13, 34],
    popupAnchor: [0, -30],
  });
}

/**
 * Pastikan map otomatis nge-fit view ke bounding box origin + destination.
 * Berjalan di client saja karena react-leaflet render post-mount.
 */
function FitBounds({ points }: { points: LatLng[] }) {
  const map = useMap();
  useEffect(() => {
    if (points.length === 0) return;
    const bounds = L.latLngBounds(points.map((p) => [p.lat, p.lng] as LatLngExpression));
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
  }, [map, points]);
  return null;
}

export default function DeliveryMap({
  origin,
  destination,
  current,
}: {
  origin: LatLng;
  destination: LatLng;
  current: LatLng;
}) {
  const originIcon = useMemo(() => makeIcon("#8fd6a4"), []);
  const destIcon = useMemo(() => makeIcon("#f2ca50"), []);
  const truckIcon = useMemo(() => makeIcon("#f5b26b"), []);

  const routeLine: LatLngExpression[] = [
    [origin.lat, origin.lng],
    [destination.lat, destination.lng],
  ];

  return (
    <MapContainer
      center={[current.lat, current.lng]}
      zoom={10}
      scrollWheelZoom={false}
      style={{ height: 320, width: "100%" }}
    >
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      <Polyline positions={routeLine} pathOptions={{ color: "#f2ca50", weight: 3, dashArray: "6 6" }} />
      <Marker position={[origin.lat, origin.lng]} icon={originIcon}>
        <Popup>Titik jemput (seller)</Popup>
      </Marker>
      <Marker position={[destination.lat, destination.lng]} icon={destIcon}>
        <Popup>Tujuan (buyer)</Popup>
      </Marker>
      <Marker position={[current.lat, current.lng]} icon={truckIcon}>
        <Popup>Posisi armada saat ini</Popup>
      </Marker>
      <FitBounds points={[origin, destination]} />
    </MapContainer>
  );
}
