export type Auth = {
  user: App.Data.Users.CurrentUserData;
  /** Portal pages only: the signed-in contact. */
  contact?: App.Data.Contacts.ContactData | null;
};
