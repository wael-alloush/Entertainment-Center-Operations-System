// src/app/store.js
import { configureStore } from "@reduxjs/toolkit";
// import invoicesReducer from "../features/invoices/InvoicesSlice";
// import rewardsReducer from "../features/rewards/RewardsSlice";
// import settingsReducer from "../features/settings/SettingsSlice";
// import dashboardReducer from "../features/dashboard/DashboardSlice";

export const store = configureStore({
  reducer: {
    // invoices: invoicesReducer,
    // rewards: rewardsReducer,
    // settings: settingsReducer,
    // dashboard: dashboardReducer,
  },
});
