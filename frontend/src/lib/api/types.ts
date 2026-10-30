// Shapes returned by the backend API. Money is always in the smallest
// currency unit (paisa for PKR); format it with formatMoney().

export type RideStatus = "requested" | "accepted" | "completed" | "cancelled";
export type RideCategory = "economy" | "comfort";
export type DriverStatus = "offline" | "available" | "on_trip";
export type UserRole = "rider" | "driver";

export interface Location {
  name: string;
  lat: number;
  lng: number;
}

export interface Place extends Location {
  id: number;
  area: string | null;
}

export interface City {
  id: number;
  name: string;
  slug: string;
  currency: string;
  center: { lat: number; lng: number };
  places: Place[];
}

export interface Driver {
  id: number;
  name: string;
  category: RideCategory;
  vehicle_model: string;
  vehicle_color: string;
  plate_number: string;
  status: DriverStatus;
  rating: number;
  location: { lat: number; lng: number } | null;
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  driver?: Driver;
}

export interface Fare {
  currency: string;
  base: number;
  distance: number;
  time: number;
  surge_multiplier: number;
  total: number;
  driver_earning?: number;
}

export interface Ride {
  id: string;
  status: RideStatus;
  category: RideCategory;
  pickup: Location;
  dropoff: Location;
  distance_meters: number;
  duration_seconds: number;
  pickup_eta_seconds: number | null;
  distance_to_pickup_meters?: number;
  fare: Fare;
  driver?: Driver | null;
  rider?: { name: string };
  cancelled_by: "rider" | "driver" | "system" | null;
  cancellation_reason: string | null;
  can_cancel: boolean;
  requested_at: string | null;
  accepted_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
}

export interface Estimate {
  category: RideCategory;
  label: string;
  currency: string;
  total_fare: number;
  distance_meters: number;
  duration_seconds: number;
  drivers_available: boolean;
  pickup_eta_seconds: number | null;
}

export interface LoginResult {
  token: string;
  token_type: "Bearer";
  user: User;
}
