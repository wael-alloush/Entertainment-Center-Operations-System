import { useEffect, useState } from "react";
import { Zap } from "lucide-react";
import React from "react";
import { Home } from "lucide-react";
import { TableOfContents } from "lucide-react";
import { Settings } from "lucide-react";
import { Info } from "lucide-react";
import { Users } from "lucide-react";
import { Mail } from "lucide-react";
import { Cloudy } from "lucide-react";
import { ClockFading } from "lucide-react";
import { ChevronDown } from "lucide-react";
import { UserCog } from "lucide-react";

const links = [
  {
    id: "dashboard",
    name: "الرئيسية",
    path: "/",
    icon: Home,
    active: true,
    badge: "جديد",
    count: 5,
    submenu: [],
  },
  {
    id: "actives",
    name: "ألفواتير النشطة",
    path: "/analytics",
    icon: TableOfContents,
    active: false,
    badge: "",
    count: 0,
    submenu: [],
  },
  {
    id: "completed",
    name: "ألفواتير السابقة",
    path: "/analytics",
    icon: ClockFading,
    active: false,
    badge: "",
    count: 0,
    submenu: [],
  },
  {
    id: "employees",
    name: "الموظفين",
    path: "/analytics",
    icon: UserCog,
    active: false,
    badge: "",
    count: 0,
    submenu: [],
  },
];

const modes = [
  { id: "", color: "bg-[#0082ce]" },
  { id: "light2", color: "bg-[#44ebd3]" },
  { id: "light3", color: "bg-[#419400]" },
  { id: "dark", color: "bg-[#99e600]" },
  { id: "dark2", color: "bg-[#3abdf7]" },
];
const Sidebar = ({
  collapsed,
  // ontogglecollapse,
  currentpage,
  onpagechange,
  onsubpagechange,
  setmode,
}) => {
  // manage expanded menus
  const [expandedMenus, setExpandedMenus] = useState(new Set(["analytics"]));
  const toggleExpandedMenu = (id) => {
    const newExpandedMenus = new Set(expandedMenus);
    if (newExpandedMenus.has(id)) {
      newExpandedMenus.delete(id);
    } else {
      newExpandedMenus.clear();
      newExpandedMenus.add(id);
    }
    setExpandedMenus(newExpandedMenus);
  };

  // close all menus when sidebar is collapsed
  useEffect(() => {
    if (collapsed) {
      setExpandedMenus(new Set());
    }
  }, [collapsed]);

  return (
    <div
      className={`${
        collapsed ? "hidden md:block md:w-20 " : " w-72"
      }  bg-base-100 text-base-content border-r border-base-300 flex flex-col transition-all ease-in-out duration-300`}
    >
      {/* logo */}
      <div className="p-5 border-b border-base-300 ">
        <div className="flex items-center space-x-3">
          <div
            className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center 
          shadow-lg"
          >
            <Zap className="text-white w-6 h-6" />
          </div>

          {/* conditional rendering */}

          {!collapsed && (
            <div>
              <h1 className="text-xl font-bold text-base-content">رعد</h1>
              <p className="text-xs text-base-content/50">لوحة تحكم</p>
            </div>
          )}
        </div>
      </div>
      {/* navigation  I will display Dynamic Menus*/}
      <nav className="flex-1 p-4 space-y-2 overflow-y-auto ">
        {links.map((link) => {
          return (
            <div key={link.id}>
              <button
                onClick={() => {
                  if (link.submenu.length > 0 && !collapsed) {
                    toggleExpandedMenu(link.id);
                    onpagechange(link.id);
                  } else {
                    onpagechange(link.id);
                  }
                }}
                className={` w-full flex items-center justify-between p-3 rounded-xl cursor-pointer
                  transition-all duration-200
                   ${
                     currentpage === link.id
                       ? "bg-primary font-medium text-primary-content shadow-md shadow-primary/30"
                       : "hover:bg-base-200"
                   }`}
              >
                <div className="flex items-center space-x-3 ">
                  <link.icon className="w-5 h-5" />
                  {/* conditional rendring */}
                  {!collapsed && (
                    <>
                      <span className="font-medium text-sm  mr-3 ">
                        {link.name}
                      </span>
                      {link.badge && (
                        <span className="px-2 py-1 text-xs bg-warning text-warning-content rounded-full">
                          {link.badge}
                        </span>
                      )}
                      {link.count > 0 && (
                        <span className="w-5 h-5 flex items-center justify-center bg-base-300 text-base-content text-xs rounded-full mr-1">
                          {link.count}
                        </span>
                      )}
                    </>
                  )}
                </div>
                {!collapsed && link.submenu.length > 0 && (
                  <ChevronDown
                    className={`w-4 h-4 mr-auto  transition-transform ${
                      expandedMenus.has(link.id) ? "transform rotate-180" : ""
                    }`}
                  />
                )}
              </button>
              {/* Submenu */}
              <div
                className={`
                      mr-8 mt-2 p-1 space-y-1 overflow-hidden transition-all duration-300 ease-in-out
                       ${
                         !collapsed && expandedMenus.has(link.id)
                           ? "max-h-40 opacity-100"
                           : "max-h-0 opacity-0"
                       }
                     `}
              >
                {!collapsed && link.submenu.length > 0 && (
                  <>
                    {link.submenu.map((sublink) => {
                      return (
                        <button
                          key={sublink.id}
                          onClick={() => onsubpagechange(sublink.id)}
                          className="w-full text-right p-2 text-sm text-base-content/50  hover:text-base-content 
                         hover:bg-base-300 rounded-lg transition-all  duration-300"
                        >
                          {sublink.name}
                        </button>
                      );
                    })}
                  </>
                )}
              </div>
            </div>
          );
        })}
      </nav>
      {/* mods */}
      {!collapsed && (
        <div className="p-4 border-t border-base-300  flex items-center space-x-3 justify-center">
          {modes.map((modeoption) => (
            <button
              key={modeoption.id}
              onClick={() => setmode(modeoption.id)}
              className={`w-4 h-4 rounded-full ${modeoption.color} transform hover:-translate-y-1
        transition duration-200 cursor-pointer`}
            ></button>
          ))}
        </div>
      )}

      {/* user profile */}
      {!collapsed && (
        <div className={`p-4 border-t border-base-300`}>
          <div
            className={`flex items-center space-x-3  rounded-xl p-3 bg-base-300 `}
          >
            <img
              src="https://plus.unsplash.com/premium_photo-1689568126014-06fea9d5d341?ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&q=80&w=1170"
              alt="user profile"
              className="w-10 h-10 rounded-full ring-2 ring-primary object-cover "
            />
            {/* conditional rendring */}

            {!collapsed && (
              <div className="flex-1 min-w-0">
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-base-content truncate">
                    وائل علوش
                  </p>
                  <p className="text-xs text-base-content/50 truncate">
                    ادارة النظام
                  </p>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default Sidebar;
